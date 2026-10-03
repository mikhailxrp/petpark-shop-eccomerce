<?php

declare(strict_types=1);

/**
 * Модель Специалистов: профиль со строкой графика и Услугами (`specialists`,
 * `specialist_services`, phase-7.md, Таск 6) и закрытые периоды
 * (`specialist_time_off`, phase-4.md, Таск 9; FR-SV-010). Только SQL через
 * PDO, возвращает массивы (php.md). Чтение периодов для расчёта слотов —
 * specialistTimeOffFrom() в Models/Booking.php.
 */

const SPECIALIST_TIME_OFF_REASON_MAX = 200;

/**
 * Профиль Специалиста: строка `specialists` и связи с Услугами. Транзакцию
 * не открывает — вызывается из userCreateStaff() внутри его транзакции, чтобы
 * логин и профиль создавались целиком или не создавались вовсе.
 *
 * @param string    $workStart  "HH:MM"
 * @param string    $workEnd    "HH:MM"
 * @param int|null  $dayOff     0=воскресенье … 6=суббота; null — выходного нет
 * @param list<int> $serviceIds активные Услуги; пусто — Специалист без Услуг
 */
function specialistCreate(int $userId, string $workStart, string $workEnd, ?int $dayOff, array $serviceIds): void
{
    $pdo = getPdo();

    $stmt = $pdo->prepare(
        'INSERT INTO specialists (user_id, work_start, work_end, day_off) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $workStart, $workEnd, $dayOff]);
    $specialistId = (int) $pdo->lastInsertId();

    $link = $pdo->prepare('INSERT INTO specialist_services (specialist_id, service_id) VALUES (?, ?)');
    foreach ($serviceIds as $serviceId) {
        $link->execute([$specialistId, $serviceId]);
    }
}

/**
 * Профиль Специалиста по `users.id` с id его Услуг; null — строки
 * `specialists` у сотрудника нет.
 *
 * @return array{id: int, work_start: string, work_end: string, day_off: ?int, service_ids: list<int>}|null
 */
function specialistFindByUserId(int $userId): ?array
{
    $pdo = getPdo();

    $stmt = $pdo->prepare('SELECT id, work_start, work_end, day_off FROM specialists WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if ($row === false) {
        return null;
    }

    $services = $pdo->prepare('SELECT service_id FROM specialist_services WHERE specialist_id = ? ORDER BY service_id');
    $services->execute([(int) $row['id']]);

    return [
        'id'          => (int) $row['id'],
        'work_start'  => (string) $row['work_start'],
        'work_end'    => (string) $row['work_end'],
        'day_off'     => $row['day_off'] === null ? null : (int) $row['day_off'],
        'service_ids' => array_map('intval', $services->fetchAll(PDO::FETCH_COLUMN)),
    ];
}

/**
 * Профиль с графиком по умолчанию (значения колонок) и без Услуг, если строки
 * `specialists` у сотрудника ещё нет. Повторный вызов ничего не меняет
 * (UNIQUE по `user_id`).
 */
function specialistEnsureForUser(int $userId): void
{
    getPdo()->prepare('INSERT IGNORE INTO specialists (user_id) VALUES (?)')->execute([$userId]);
}

/**
 * Сохранить график и заменить набор Услуг Специалиста. Одна транзакция с
 * блокировкой строки Специалиста — той же, что в bookingCreate(), поэтому
 * параллельная Запись видит либо старый график, либо новый целиком. Если
 * профиля ещё нет (роль сменили до Таска 13), он создаётся здесь же.
 *
 * @param string    $workStart "HH:MM"
 * @param string    $workEnd   "HH:MM"
 * @param int|null  $dayOff    0=воскресенье … 6=суббота; null — выходного нет
 * @param list<int> $serviceIds активные Услуги; пусто — Специалист без Услуг
 */
function specialistUpdate(int $userId, string $workStart, string $workEnd, ?int $dayOff, array $serviceIds): void
{
    $pdo = getPdo();
    $pdo->beginTransaction();

    try {
        specialistEnsureForUser($userId);

        $lock = $pdo->prepare('SELECT id FROM specialists WHERE user_id = ? FOR UPDATE');
        $lock->execute([$userId]);
        $specialistId = (int) $lock->fetchColumn();

        $pdo->prepare('UPDATE specialists SET work_start = ?, work_end = ?, day_off = ? WHERE id = ?')
            ->execute([$workStart, $workEnd, $dayOff, $specialistId]);

        $pdo->prepare('DELETE FROM specialist_services WHERE specialist_id = ?')->execute([$specialistId]);

        $link = $pdo->prepare('INSERT INTO specialist_services (specialist_id, service_id) VALUES (?, ?)');
        foreach ($serviceIds as $serviceId) {
            $link->execute([$specialistId, $serviceId]);
        }

        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Будущие Записи Специалиста, занимающие слот (подтверждённые и удержание,
 * ждущее оплаты), с Услугами — для проверки по графику после его правки.
 *
 * @return list<array{id: int, scheduled_at: string, client_name: string, duration_minutes: int, service_ids: list<int>, kinds: list<string>}>
 */
function specialistFutureBookings(int $specialistId): array
{
    $stmt = getPdo()->prepare(
        "SELECT b.id, b.scheduled_at, u.name AS client_name,
                COALESCE(SUM(bs.duration_minutes), 0) AS duration_minutes,
                GROUP_CONCAT(bs.service_id) AS service_ids,
                GROUP_CONCAT(DISTINCT s.kind) AS kinds
         FROM bookings b
         JOIN users u ON u.id = b.user_id
         LEFT JOIN booking_services bs ON bs.booking_id = b.id
         LEFT JOIN services s ON s.id = bs.service_id
         WHERE b.specialist_id = ?
           AND b.scheduled_at >= NOW()
           AND (b.status = 'confirmed' OR (b.status = 'slot_selected' AND b.slot_hold_expires_at > NOW()))
         GROUP BY b.id, b.scheduled_at, u.name
         ORDER BY b.scheduled_at, b.id"
    );
    $stmt->execute([$specialistId]);

    return array_map(
        static fn (array $row): array => [
            'id'               => (int) $row['id'],
            'scheduled_at'     => (string) $row['scheduled_at'],
            'client_name'      => (string) $row['client_name'],
            'duration_minutes' => (int) $row['duration_minutes'],
            'service_ids'      => $row['service_ids'] === null ? [] : array_map('intval', explode(',', (string) $row['service_ids'])),
            'kinds'            => $row['kinds'] === null ? [] : explode(',', (string) $row['kinds']),
        ],
        $stmt->fetchAll()
    );
}

/**
 * Не закончившиеся закрытые периоды: всех Специалистов (null) или одного.
 *
 * @return list<array{id: int, specialist_id: int, specialist_name: string, date_from: string, date_to: string, reason: ?string}>
 */
function specialistTimeOffList(?int $specialistId): array
{
    $stmt = getPdo()->prepare(
        'SELECT t.id, t.specialist_id, u.name AS specialist_name, t.date_from, t.date_to, t.reason
         FROM specialist_time_off t
         JOIN specialists sp ON sp.id = t.specialist_id
         JOIN users u ON u.id = sp.user_id
         WHERE t.date_to >= CURDATE() AND (? IS NULL OR t.specialist_id = ?)
         ORDER BY t.date_from, t.id'
    );
    $stmt->execute([$specialistId, $specialistId]);

    return array_map(
        static fn (array $row): array => [
            'id'              => (int) $row['id'],
            'specialist_id'   => (int) $row['specialist_id'],
            'specialist_name' => (string) $row['specialist_name'],
            'date_from'       => (string) $row['date_from'],
            'date_to'         => (string) $row['date_to'],
            'reason'          => $row['reason'] === null ? null : (string) $row['reason'],
        ],
        $stmt->fetchAll()
    );
}

/**
 * Закрыть дни Специалиста. Одна транзакция с блокировкой строки Специалиста
 * (та же, что в bookingCreate()), поэтому параллельная Запись на эти дни и
 * закрытие не пройдут одновременно. Отклоняется, если в диапазоне есть
 * подтверждённая Запись или удержание слота, ждущее оплаты.
 *
 * @param string $dateFrom "Y-m-d"
 * @param string $dateTo   "Y-m-d", не раньше $dateFrom
 * @return 'created'|'booking_conflict'|'invalid'
 */
function specialistTimeOffCreate(int $specialistId, string $dateFrom, string $dateTo, ?string $reason): string
{
    $pdo = getPdo();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('SELECT id FROM specialists WHERE id = ? FOR UPDATE');
        $stmt->execute([$specialistId]);
        if ($stmt->fetchColumn() === false) {
            $pdo->rollBack();
            return 'invalid';
        }

        $conflict = $pdo->prepare(
            "SELECT 1 FROM bookings
             WHERE specialist_id = ?
               AND scheduled_at >= ? AND scheduled_at < DATE_ADD(?, INTERVAL 1 DAY)
               AND (status = 'confirmed' OR (status = 'slot_selected' AND slot_hold_expires_at > NOW()))
             LIMIT 1"
        );
        $conflict->execute([$specialistId, $dateFrom, $dateTo]);
        if ($conflict->fetchColumn() !== false) {
            $pdo->rollBack();
            return 'booking_conflict';
        }

        $insert = $pdo->prepare(
            'INSERT INTO specialist_time_off (specialist_id, date_from, date_to, reason) VALUES (?, ?, ?, ?)'
        );
        $insert->execute([$specialistId, $dateFrom, $dateTo, $reason]);

        $pdo->commit();

        return 'created';
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Открыть дни снова. $specialistId — владелец периода (null — любой, для
 * админов); чужой или несуществующий период не удаляется.
 */
function specialistTimeOffDelete(int $id, ?int $specialistId): bool
{
    $stmt = getPdo()->prepare(
        'DELETE FROM specialist_time_off WHERE id = ? AND (? IS NULL OR specialist_id = ?)'
    );
    $stmt->execute([$id, $specialistId, $specialistId]);

    return $stmt->rowCount() === 1;
}
