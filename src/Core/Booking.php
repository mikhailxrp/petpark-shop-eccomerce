<?php

declare(strict_types=1);

/**
 * Чистые правила Записи: сетка и свободные слоты Специалиста, переходы
 * статусов. Без БД и без HTTP — покрыто tests/Unit/BookingTest.php.
 * Выборка занятых интервалов и отпусков из БД — Models/Booking.php (Таск 3).
 */

/**
 * Допустимые переходы bookings.status — диаграмма «Запись», tz.md §6.3.
 * slot_released/cancelled/no_show/completed — конечные. Перенос — это отмена
 * + новая Запись (phase-4.md), а не смена статуса на «перенесена».
 */
const BOOKING_STATUS_TRANSITIONS = [
    'slot_selected' => ['confirmed', 'slot_released'],
    'confirmed'     => ['cancelled', 'no_show', 'completed'],
    'slot_released' => [],
    'cancelled'     => [],
    'no_show'       => [],
    'completed'     => [],
];

// Статусы, при которых слот занят для других Покупателей (FR-SV-007, Q-036).
const BOOKING_SLOT_OCCUPYING_STATUSES = ['slot_selected', 'confirmed'];

const BOOKING_HORIZON_DAYS = 30;     // FR-SV-003: запись не дальше чем на 30 дней вперёд
const BOOKING_SLOT_STEP_MINUTES = 30; // начало визита — в :00 и :30
const BOOKING_GROOMING_BUFFER_MINUTES = 15; // FR-SV-003: буфер после груминга

const BOOKING_KIND_GROOMING = 'grooming';

/** Допустим ли переход статуса Записи. Неизвестный статус — не допустим. */
function bookingCanTransition(string $from, string $to): bool
{
    return in_array($to, BOOKING_STATUS_TRANSITIONS[$from] ?? [], true);
}

/**
 * Сколько минут Специалист занят одной Записью: длительность Услуг плюс
 * буфер, если среди Услуг есть груминг. Буфер ветеринара — 0.
 *
 * @param list<string> $kinds services.kind всех Услуг Записи
 */
function bookingBlockMinutes(int $durationMinutes, array $kinds): int
{
    if ($durationMinutes <= 0) {
        throw new InvalidArgumentException('Длительность Записи должна быть больше нуля');
    }

    $buffer = in_array(BOOKING_KIND_GROOMING, $kinds, true) ? BOOKING_GROOMING_BUFFER_MINUTES : 0;

    return $durationMinutes + $buffer;
}

/** "10:00" / "10:00:00" → минуты от полуночи. */
function bookingTimeToMinutes(string $time): int
{
    if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $time, $m) !== 1) {
        throw new InvalidArgumentException("Некорректное время «{$time}»");
    }

    return (int) $m[1] * 60 + (int) $m[2];
}

/**
 * Свободные начала визита Специалиста на дату, по возрастанию, формат "HH:MM".
 *
 * Слот свободен, если блок [начало; начало + $blockMinutes) помещается в
 * рабочий день, не пересекает занятые интервалы и дата не закрыта.
 * Пустой список — дата вне горизонта, выходной, отпуск или нет места.
 *
 * @param array{work_start: string, work_end: string, day_off: int|string|null} $specialist
 * @param string $date                 "Y-m-d"
 * @param int    $blockMinutes         bookingBlockMinutes() новой Записи
 * @param list<array{start: string, block_minutes: int}> $busy занятые Записи
 *        (status из BOOKING_SLOT_OCCUPYING_STATUSES): start "Y-m-d H:i[:s]"
 * @param list<array{date_from: string, date_to: string}> $timeOff specialist_time_off
 */
function bookingFreeSlots(
    array $specialist,
    string $date,
    int $blockMinutes,
    array $busy,
    array $timeOff,
    DateTimeImmutable $now
): array {
    $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if ($day === false || $day->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException("Некорректная дата «{$date}»");
    }
    if ($blockMinutes <= 0) {
        throw new InvalidArgumentException('Длительность блока должна быть больше нуля');
    }

    $today = $now->setTime(0, 0);
    $lastDay = $today->modify('+' . BOOKING_HORIZON_DAYS . ' days');
    if ($day < $today || $day > $lastDay) {
        return [];
    }

    $dayOff = $specialist['day_off'] ?? null;
    if ($dayOff !== null && (int) $day->format('w') === (int) $dayOff) {
        return [];
    }

    foreach ($timeOff as $period) {
        if ($date >= $period['date_from'] && $date <= $period['date_to']) {
            return [];
        }
    }

    $busyIntervals = [];
    foreach ($busy as $booking) {
        $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', strlen($booking['start']) === 16
            ? $booking['start'] . ':00'
            : $booking['start']);
        if ($start === false) {
            throw new InvalidArgumentException("Некорректное время занятой Записи «{$booking['start']}»");
        }
        // Минуты от полуночи запрашиваемой даты; запись другого дня даёт вне-дневной интервал
        $offset = intdiv($start->getTimestamp() - $day->getTimestamp(), 60);
        $busyIntervals[] = [$offset, $offset + $booking['block_minutes']];
    }

    $workStart = bookingTimeToMinutes($specialist['work_start']);
    $workEnd = bookingTimeToMinutes($specialist['work_end']);
    // Сегодня — только слоты позже текущего момента
    $earliest = $day == $today
        ? intdiv($now->getTimestamp() - $day->getTimestamp(), 60)
        : 0;

    $slots = [];
    for ($slot = $workStart; $slot + $blockMinutes <= $workEnd; $slot += BOOKING_SLOT_STEP_MINUTES) {
        if ($slot <= $earliest) {
            continue;
        }

        $slotEnd = $slot + $blockMinutes;
        $overlaps = false;
        foreach ($busyIntervals as [$busyStart, $busyEnd]) {
            if ($slot < $busyEnd && $slotEnd > $busyStart) {
                $overlaps = true;
                break;
            }
        }

        if (!$overlaps) {
            $slots[] = sprintf('%02d:%02d', intdiv($slot, 60), $slot % 60);
        }
    }

    return $slots;
}
