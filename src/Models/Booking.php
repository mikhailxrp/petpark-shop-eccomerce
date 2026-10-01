<?php

declare(strict_types=1);

/**
 * Модель Записей — только SQL через PDO, возвращает массивы (php.md).
 * Чтение для расчёта свободных слотов (`FR-SV-003`) и создание Записи
 * (`FR-SV-006/007`, Таск 4). `database.md` (`bookings`, `booking_services`,
 * `specialist_time_off`).
 */

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
