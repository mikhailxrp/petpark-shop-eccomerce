<?php

declare(strict_types=1);

/**
 * Модель Записей — только SQL через PDO, возвращает массивы (php.md).
 * Чтение для расчёта свободных слотов (`FR-SV-003`) и создание Записи
 * (`FR-SV-006/007`, Таск 4). `database.md` (`bookings`, `booking_services`,
 * `specialist_time_off`).
 */

const BOOKINGS_UPCOMING_LIMIT = 50; // «Мои записи»: потолок списка

/**
 * Занятые интервалы Специалиста на дату в формате, который ждёт
 * bookingFreeSlots(): начало и длина блока (Услуги + буфер груминга).
 * Занимают слот статусы из BOOKING_SLOT_OCCUPYING_STATUSES.
 *
 * @return list<array{start: string, block_minutes: int}>
 */
function bookingsBusyForDay(int $specialistId, string $date): array
{
    $statuses = BOOKING_SLOT_OCCUPYING_STATUSES;
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));

    $stmt = getPdo()->prepare(
        "SELECT b.scheduled_at AS start,
                COALESCE(SUM(bs.duration_minutes), 0) AS duration,
                COALESCE(MAX(s.kind = 'grooming'), 0) AS has_grooming
         FROM bookings b
         LEFT JOIN booking_services bs ON bs.booking_id = b.id
         LEFT JOIN services s ON s.id = bs.service_id
         WHERE b.specialist_id = ?
           AND b.status IN ({$placeholders})
           AND b.scheduled_at >= ?
           AND b.scheduled_at < DATE_ADD(?, INTERVAL 1 DAY)
         GROUP BY b.id, b.scheduled_at"
    );
    $stmt->execute([$specialistId, ...$statuses, $date, $date]);

    $busy = [];
    foreach ($stmt->fetchAll() as $row) {
        $duration = (int) $row['duration'];
        // Запись без строк booking_services — повреждённые данные; слот
        // всё равно считаем занятым (один шаг сетки), а не падаем.
        $block = $duration > 0
            ? bookingBlockMinutes($duration, (bool) $row['has_grooming'] ? [BOOKING_KIND_GROOMING] : [])
            : BOOKING_SLOT_STEP_MINUTES;

        $busy[] = ['start' => (string) $row['start'], 'block_minutes' => $block];
    }

    return $busy;
}

/**
 * Закрытые периоды Специалиста (отпуск, болезнь), не закончившиеся до даты.
 *
 * @return list<array{date_from: string, date_to: string}>
 */
function specialistTimeOffFrom(int $specialistId, string $date): array
{
    $stmt = getPdo()->prepare(
        'SELECT date_from, date_to FROM specialist_time_off
         WHERE specialist_id = ? AND date_to >= ?'
    );
    $stmt->execute([$specialistId, $date]);

    return $stmt->fetchAll();
}

/**
 * Создаёт Запись одной транзакцией (FR-SV-006/007, FR-AUTH-002).
 *
 * Первым в транзакции идёт блокирующее чтение строки Специалиста
 * (`FOR UPDATE`): параллельные отправки на одного Специалиста встают в
 * очередь, а снимок обычных SELECT-ов InnoDB (REPEATABLE READ) строится
 * только после получения блокировки — вторая отправка видит уже
 * зафиксированную Запись первой и получает `slot_taken`. Любое чтение до
 * блокировки сломало бы это, поэтому его здесь нет.
 *
 * Расчёт слота — тот же bookingFreeSlots(), что у /booking/slots, а не
 * отдельная SQL-проверка пересечений (одна логика на два места).
 * Депозит — один на Запись: наибольший из `services.deposit_amount`.
 * Без Депозита Запись сразу `confirmed`, с Депозитом — `slot_selected`
 * с удержанием слота на BOOKING_SLOT_HOLD_MINUTES.
 *
 * @param array<int, array<string, mixed>> $services строки servicesFindActiveByIds(), порядок = «подряд»
 * @param array{name: string, phone: string, email: string} $contact
 * @param array{name: string, species: string, breed: ?string, weight: ?string}|null $newPet null — выбран существующий
 * @return array{status: 'created', booking_id: int, booking_status: string, new_account: array{name: string, email: string, password: string}|null}
 *       | array{status: 'slot_taken'|'invalid'}
 */
function bookingCreate(
    ?int $sessionUserId,
    array $contact,
    int $specialistId,
    string $date,
    string $time,
    array $services,
    ?int $petId,
    ?array $newPet
): array {
    $pdo = getPdo();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('SELECT id, work_start, work_end, day_off FROM specialists WHERE id = ? FOR UPDATE');
        $stmt->execute([$specialistId]);
        $specialist = $stmt->fetch();
        if ($specialist === false) {
            $pdo->rollBack();
            return ['status' => 'invalid'];
        }

        // Истёкшее удержание не должно занимать слот (FR-SV-009, без cron).
        bookingReleaseExpired($specialistId);

        $block = bookingBlockMinutes(
            array_sum(array_map(static fn (array $s): int => (int) $s['duration_minutes'], $services)),
            array_column($services, 'kind')
        );
        $freeSlots = bookingFreeSlots(
            $specialist,
            $date,
            $block,
            bookingsBusyForDay($specialistId, $date),
            specialistTimeOffFrom($specialistId, $date),
            new DateTimeImmutable('now')
        );
        if (!in_array($time, $freeSlots, true)) {
            $pdo->rollBack();
            return ['status' => 'slot_taken'];
        }

        $newAccount = null;
        $userId = $sessionUserId;
        if ($userId === null) {
            $existingUser = userFindByEmail($contact['email']);
            if ($existingUser !== null) {
                $userId = (int) $existingUser['id'];
            } else {
                $password = generatePassword();
                $userId = userCreateCustomer(
                    $contact['name'],
                    $contact['email'],
                    $contact['phone'],
                    password_hash($password, PASSWORD_DEFAULT)
                );
                $newAccount = ['name' => $contact['name'], 'email' => $contact['email'], 'password' => $password];
            }
        }

        if ($newPet !== null) {
            $petId = petCreate($userId, $newPet['name'], $newPet['species'], $newPet['breed'], $newPet['weight']);
        } elseif ($petId === null || petFind($userId, $petId) === null) {
            $pdo->rollBack();
            return ['status' => 'invalid'];
        }

        $deposit = null;
        foreach ($services as $service) {
            if ($service['deposit_amount'] !== null
                && ($deposit === null || orderMoneyToKopecks((string) $service['deposit_amount']) > orderMoneyToKopecks($deposit))
            ) {
                $deposit = (string) $service['deposit_amount'];
            }
        }
        $bookingStatus = $deposit === null ? 'confirmed' : 'slot_selected';

        $pdo->prepare('
            INSERT INTO bookings (
                user_id, pet_id, specialist_id, scheduled_at, status,
                slot_hold_expires_at, deposit_amount, deposit_status
            ) VALUES (
                :user_id, :pet_id, :specialist_id, :scheduled_at, :status,
                IF(:has_deposit = 1, DATE_ADD(NOW(), INTERVAL :hold_minutes MINUTE), NULL), :deposit_amount, \'none\'
            )
        ')->execute([
            'user_id'        => $userId,
            'pet_id'         => $petId,
            'specialist_id'  => $specialistId,
            'scheduled_at'   => $date . ' ' . $time . ':00',
            'status'         => $bookingStatus,
            'has_deposit'    => $deposit === null ? 0 : 1,
            'hold_minutes'   => BOOKING_SLOT_HOLD_MINUTES,
            'deposit_amount' => $deposit,
        ]);
        $bookingId = (int) $pdo->lastInsertId();

        $itemStmt = $pdo->prepare('
            INSERT INTO booking_services (booking_id, service_id, service_name, price, duration_minutes, sort_order)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        foreach (array_values($services) as $position => $service) {
            $itemStmt->execute([
                $bookingId,
                (int) $service['id'],
                (string) $service['name'],
                (string) $service['price'],
                (int) $service['duration_minutes'],
                $position,
            ]);
        }

        $pdo->commit();

        return [
            'status'         => 'created',
            'booking_id'     => $bookingId,
            'booking_status' => $bookingStatus,
            'new_account'    => $newAccount,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

/**
 * Запись для страницы успеха: Питомец, Специалист, Услуги-снэпшоты.
 *
 * @return array<string, mixed>|null
 */
function bookingFindById(int $id): ?array
{
    $pdo = getPdo();
    $stmt = $pdo->prepare('
        SELECT b.id, b.user_id, b.scheduled_at, b.status, b.slot_hold_expires_at,
               b.deposit_amount, b.deposit_status, b.amocrm_id,
               p.name AS pet_name, u.name AS specialist_name
        FROM bookings b
        JOIN pets p ON p.id = b.pet_id
        JOIN specialists sp ON sp.id = b.specialist_id
        JOIN users u ON u.id = sp.user_id
        WHERE b.id = ?
    ');
    $stmt->execute([$id]);
    $booking = $stmt->fetch();
    if ($booking === false) {
        return null;
    }

    $itemsStmt = $pdo->prepare('
        SELECT service_name, price, duration_minutes
        FROM booking_services
        WHERE booking_id = ?
        ORDER BY sort_order
    ');
    $itemsStmt->execute([$id]);
    $booking['services'] = $itemsStmt->fetchAll();

    return $booking;
}

/** Идентификатор сделки заглушки AmoCRM (ADR-023) и время «синхронизации». */
function bookingSetAmoCrm(int $bookingId, string $amocrmId): void
{
    getPdo()->prepare('UPDATE bookings SET amocrm_id = ?, amocrm_synced_at = NOW() WHERE id = ?')
        ->execute([$amocrmId, $bookingId]);
}

/**
 * Освобождает удержания, оплата которых не пришла за BOOKING_SLOT_HOLD_MINUTES
 * (FR-SV-009). Вместо cron вызывается «лениво» — там, где слот читают или
 * занимают. Идемпотентно; $specialistId сужает выборку до одного Специалиста.
 * Возвращает число освобождённых Записей.
 */
function bookingReleaseExpired(?int $specialistId = null): int
{
    $stmt = getPdo()->prepare("
        UPDATE bookings SET status = 'slot_released'
        WHERE status = 'slot_selected'
          AND slot_hold_expires_at < NOW()
          AND (:specialist_id IS NULL OR specialist_id = :specialist_id2)
    ");
    $stmt->execute(['specialist_id' => $specialistId, 'specialist_id2' => $specialistId]);

    return $stmt->rowCount();
}

/**
 * Оплата Депозита прошла: `slot_selected` → `confirmed`, Депозит удержан.
 * false — Запись уже подтверждена, отпущена или удержание истекло (повторный
 * callback и оплата «после срока» — не ошибка, статус не меняется).
 */
function bookingConfirmWithDeposit(int $bookingId): bool
{
    $stmt = getPdo()->prepare("
        UPDATE bookings SET status = 'confirmed', deposit_status = 'held', slot_hold_expires_at = NULL
        WHERE id = ? AND status = 'slot_selected' AND slot_hold_expires_at > NOW()
    ");
    $stmt->execute([$bookingId]);

    return $stmt->rowCount() === 1;
}

/**
 * Покупатель отказался от неоплаченного удержания: `slot_selected` →
 * `slot_released`. Депозит не вносился — возвращать нечего. false — Запись
 * уже оплачена или отпущена. Отмена подтверждённой Записи — Таск 6.
 */
function bookingReleaseHold(int $bookingId): bool
{
    $stmt = getPdo()->prepare("
        UPDATE bookings SET status = 'slot_released'
        WHERE id = ? AND status = 'slot_selected'
    ");
    $stmt->execute([$bookingId]);

    return $stmt->rowCount() === 1;
}

/**
 * Лог каждого callback'а оплаты Депозита (php.md: логировать все вебхуки).
 * В `payment_logs` заполнено ровно одно из `order_id` / `booking_id`: здесь
 * `order_id` всегда NULL, у Заказа — `orderPaymentLogCreate()` с NULL в
 * `booking_id`; поэтому функции две, а не одна с двумя необязательными id.
 */
function bookingPaymentLogCreate(int $bookingId, string $provider, bool $signatureValid, string $payload): void
{
    $stmt = getPdo()->prepare('
        INSERT INTO payment_logs (order_id, booking_id, provider, signature_valid, payload)
        VALUES (NULL, :booking_id, :provider, :signature_valid, :payload)
    ');
    $stmt->execute([
        'booking_id'      => $bookingId,
        'provider'        => $provider,
        'signature_valid' => $signatureValid ? 1 : 0,
        'payload'         => $payload,
    ]);
}

/**
 * Ждущие оплаты Депозита Записи Покупателя с неистёкшим удержанием,
 * сгруппированные по Питомцу: [pet_id => список Записей]. Для карточек
 * Питомцев в кабинете.
 *
 * @return array<int, list<array<string, mixed>>>
 */
function bookingsPendingByPet(int $userId): array
{
    $stmt = getPdo()->prepare("
        SELECT id, pet_id, scheduled_at, slot_hold_expires_at, deposit_amount
        FROM bookings
        WHERE user_id = ? AND status = 'slot_selected' AND slot_hold_expires_at > NOW()
        ORDER BY scheduled_at
    ");
    $stmt->execute([$userId]);

    $byPet = [];
    foreach ($stmt->fetchAll() as $row) {
        $byPet[(int) $row['pet_id']][] = $row;
    }

    return $byPet;
}

/**
 * Переход статуса Записи по карте `BOOKING_STATUS_TRANSITIONS`. Условный
 * UPDATE по прежнему статусу: из двух параллельных отмен сработает одна.
 * false — переход недопустим или Запись уже в другом статусе / не найдена.
 * Единственное место смены статуса подтверждённой Записи (Таски 6–8).
 */
function bookingTransition(int $bookingId, string $to): bool
{
    $pdo = getPdo();

    $stmt = $pdo->prepare('SELECT status FROM bookings WHERE id = ?');
    $stmt->execute([$bookingId]);
    $from = $stmt->fetchColumn();

    if ($from === false || !bookingCanTransition((string) $from, $to)) {
        return false;
    }

    $update = $pdo->prepare('UPDATE bookings SET status = ? WHERE id = ? AND status = ?');
    $update->execute([$to, $bookingId, $from]);

    return $update->rowCount() === 1;
}

/** Депозит возвращён: `held` → `returned` у отменённой Записи. false — возвращать было нечего. */
function bookingMarkDepositReturned(int $bookingId): bool
{
    $stmt = getPdo()->prepare("
        UPDATE bookings SET deposit_status = 'returned'
        WHERE id = ? AND status = 'cancelled' AND deposit_status = 'held'
    ");
    $stmt->execute([$bookingId]);

    return $stmt->rowCount() === 1;
}

/**
 * Ближайшие подтверждённые Записи Покупателя (FR-SV-008) — не дальше
 * горизонта записи, поэтому список короткий, но с LIMIT про запас.
 *
 * @return list<array<string, mixed>>
 */
function bookingsUpcomingByUser(int $userId): array
{
    $stmt = getPdo()->prepare("
        SELECT b.id, b.scheduled_at, b.deposit_amount, b.deposit_status,
               p.name AS pet_name, u.name AS specialist_name,
               (SELECT GROUP_CONCAT(bs.service_name ORDER BY bs.sort_order SEPARATOR ', ')
                FROM booking_services bs WHERE bs.booking_id = b.id) AS service_names
        FROM bookings b
        JOIN pets p ON p.id = b.pet_id
        JOIN specialists sp ON sp.id = b.specialist_id
        JOIN users u ON u.id = sp.user_id
        WHERE b.user_id = ? AND b.status = 'confirmed' AND b.scheduled_at >= NOW()
        ORDER BY b.scheduled_at
        LIMIT " . BOOKINGS_UPCOMING_LIMIT . '
    ');
    $stmt->execute([$userId]);

    return $stmt->fetchAll();
}

/** Статусы Записей, которые видит персонал в календаре; `slot_released` — освобождённый слот, не показываем. */
const BOOKING_CALENDAR_STATUSES = ['slot_selected', 'confirmed', 'completed', 'no_show', 'cancelled'];

/** specialists.id по логину специалиста; null — у пользователя нет профиля Специалиста. */
function bookingSpecialistIdByUser(int $userId): ?int
{
    $stmt = getPdo()->prepare('SELECT id FROM specialists WHERE user_id = ?');
    $stmt->execute([$userId]);
    $id = $stmt->fetchColumn();

    return $id === false ? null : (int) $id;
}

/**
 * Специалисты для фильтра календаря.
 *
 * @return list<array{id: int, name: string}>
 */
function bookingSpecialistsForFilter(): array
{
    $rows = getPdo()->query('
        SELECT sp.id, u.name
        FROM specialists sp
        JOIN users u ON u.id = sp.user_id
        ORDER BY u.name
    ')->fetchAll();

    return array_map(
        static fn (array $row): array => ['id' => (int) $row['id'], 'name' => (string) $row['name']],
        $rows
    );
}

/**
 * Записи за неделю для календаря персонала (FR-SV-010) — один запрос без
 * N+1: Услуги склеены подзапросом. $specialistId — фильтр (null = все).
 *
 * @param string $from "Y-m-d", начало недели (включительно)
 * @param string $to   "Y-m-d", конец недели (включительно)
 * @return list<array<string, mixed>>
 */
function bookingsForWeek(string $from, string $to, ?int $specialistId): array
{
    $statuses = BOOKING_CALENDAR_STATUSES;
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $params = [...$statuses, $from, $to];

    $specialistSql = '';
    if ($specialistId !== null) {
        $specialistSql = 'AND b.specialist_id = ?';
        $params[] = $specialistId;
    }

    $stmt = getPdo()->prepare("
        SELECT b.id, b.scheduled_at, b.status, b.deposit_status, b.specialist_id,
               p.name AS pet_name, c.name AS customer_name, su.name AS specialist_name,
               (SELECT GROUP_CONCAT(bs.service_name ORDER BY bs.sort_order SEPARATOR ', ')
                FROM booking_services bs WHERE bs.booking_id = b.id) AS service_names
        FROM bookings b
        JOIN pets p ON p.id = b.pet_id
        JOIN users c ON c.id = b.user_id
        JOIN specialists sp ON sp.id = b.specialist_id
        JOIN users su ON su.id = sp.user_id
        WHERE b.status IN ({$placeholders})
          AND b.scheduled_at >= ?
          AND b.scheduled_at < DATE_ADD(?, INTERVAL 1 DAY)
          {$specialistSql}
        ORDER BY b.scheduled_at, b.id
    ");
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/**
 * Запись для карточки персонала: клиент, Питомец, Специалист (с
 * `specialist_id` для проверки владельца), Услуги, Депозит. Статус не
 * фильтруется — решает вызывающий код.
 *
 * @return array<string, mixed>|null
 */
function bookingFindForStaff(int $id): ?array
{
    $pdo = getPdo();
    $stmt = $pdo->prepare('
        SELECT b.id, b.scheduled_at, b.status, b.deposit_amount, b.deposit_status,
               b.amocrm_id, b.specialist_id, b.created_by_user_id,
               p.name AS pet_name, p.species AS pet_species,
               c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
               su.name AS specialist_name
        FROM bookings b
        JOIN pets p ON p.id = b.pet_id
        JOIN users c ON c.id = b.user_id
        JOIN specialists sp ON sp.id = b.specialist_id
        JOIN users su ON su.id = sp.user_id
        WHERE b.id = ?
    ');
    $stmt->execute([$id]);
    $booking = $stmt->fetch();
    if ($booking === false) {
        return null;
    }

    $itemsStmt = $pdo->prepare('
        SELECT service_name, price, duration_minutes
        FROM booking_services
        WHERE booking_id = ?
        ORDER BY sort_order
    ');
    $itemsStmt->execute([$id]);
    $booking['services'] = $itemsStmt->fetchAll();

    return $booking;
}

/** Неявка: Депозит не возвращается — `held` → `forfeited` у Записи в `no_show`. false — удерживать было нечего. */
function bookingMarkDepositForfeited(int $bookingId): bool
{
    $stmt = getPdo()->prepare("
        UPDATE bookings SET deposit_status = 'forfeited'
        WHERE id = ? AND status = 'no_show' AND deposit_status = 'held'
    ");
    $stmt->execute([$bookingId]);

    return $stmt->rowCount() === 1;
}
