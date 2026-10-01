<?php

declare(strict_types=1);

/**
 * Модель закрытых периодов Специалистов (`specialist_time_off`, phase-4.md,
 * Таск 9; FR-SV-010). Только SQL через PDO, возвращает массивы (php.md).
 * Чтение периодов для расчёта слотов — specialistTimeOffFrom() в
 * Models/Booking.php.
 */

const SPECIALIST_TIME_OFF_REASON_MAX = 200;

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
