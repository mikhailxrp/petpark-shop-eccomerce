<?php

declare(strict_types=1);

/**
 * Модель Записей — только SQL через PDO, возвращает массивы (php.md).
 * Пока только чтение для расчёта свободных слотов (`FR-SV-003`);
 * создание Записи — Таск 4. `database.md` (`bookings`, `booking_services`,
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
