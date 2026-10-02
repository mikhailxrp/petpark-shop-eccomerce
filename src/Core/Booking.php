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
 * Может ли Покупатель сам отменить Запись: до визита осталось не меньше
 * $thresholdHours часов (FR-SV-008; ровно 3:00 — ещё можно). Персонал порог
 * не проверяет — форс-мажорная отмена (phase-4.md, Таск 7).
 */
function bookingCanCancelByCustomer(DateTimeImmutable $scheduledAt, DateTimeImmutable $now, int $thresholdHours): bool
{
    return $scheduledAt->getTimestamp() - $now->getTimestamp() >= $thresholdHours * 3600;
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

/**
 * Границы недели (понедельник–воскресенье, ISO) для любой даты внутри неё —
 * календарь Записей персонала (FR-SV-010).
 *
 * @param string $date "Y-m-d"
 * @return array{from: string, to: string} оба "Y-m-d", включительно
 */
function bookingWeekBounds(string $date): array
{
    $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

    if ($day === false || $day->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException("Некорректная дата «{$date}»");
    }

    $monday = $day->modify('-' . ((int) $day->format('N') - 1) . ' days');

    return [
        'from' => $monday->format('Y-m-d'),
        'to'   => $monday->modify('+6 days')->format('Y-m-d'),
    ];
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
 * Ошибки графика нового Специалиста (phase-7.md, Таск 6): время — "HH:MM",
 * конец работы позже начала, выходной — пусто (нет выходного) или 0–6
 * (0 — воскресенье, как `specialists.day_off`). Пустой массив — график верен.
 *
 * @return array<string, string> ошибки по полям work_start / work_end / day_off
 */
function specialistScheduleErrors(string $workStart, string $workEnd, string $dayOff): array
{
    $errors = [];
    $timePattern = '/^([01]\d|2[0-3]):[0-5]\d$/';

    if (preg_match($timePattern, $workStart) !== 1) {
        $errors['work_start'] = 'Укажите время начала работы.';
    }
    if (preg_match($timePattern, $workEnd) !== 1) {
        $errors['work_end'] = 'Укажите время окончания работы.';
    }
    if ($errors === [] && bookingTimeToMinutes($workEnd) <= bookingTimeToMinutes($workStart)) {
        $errors['work_end'] = 'Конец работы должен быть позже начала.';
    }
    if ($dayOff !== '' && preg_match('/^[0-6]$/', $dayOff) !== 1) {
        $errors['day_off'] = 'Выберите выходной из списка.';
    }

    return $errors;
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

/** Подписи bookings.status для Покупателя (FR-ACC-002). */
const BOOKING_STATUS_LABELS = [
    'confirmed' => 'Подтверждена',
    'completed' => 'Состоялась',
    'no_show'   => 'Неявка',
    'cancelled' => 'Отменена',
];

/** Подписи bookings.deposit_status; `none` — строки «Депозит» нет вовсе. */
const BOOKING_DEPOSIT_LABELS = [
    'held'      => 'Внесён, вернём при отмене',
    'returned'  => 'Возвращён',
    'forfeited' => 'Не возвращается (неявка)',
];

/** Подпись статуса Записи; неизвестный статус выводится как есть. */
function bookingStatusLabel(string $status): string
{
    return BOOKING_STATUS_LABELS[$status] ?? $status;
}

/** Подпись Депозита; null — Депозита не было, показывать нечего. */
function bookingDepositLabel(string $depositStatus): ?string
{
    return BOOKING_DEPOSIT_LABELS[$depositStatus] ?? null;
}

/** Предстоящая Запись: подтверждена и визит ещё не начался. */
function bookingIsUpcoming(array $booking, DateTimeImmutable $now): bool
{
    return $booking['status'] === 'confirmed'
        && new DateTimeImmutable((string) $booking['scheduled_at']) >= $now;
}

/**
 * Записи по Питомцам для «Мои записи» (FR-ACC-002). Каждый Питомец из $pets
 * попадает в результат, даже без Записей; Запись с неизвестным `pet_id`
 * отбрасывается. Внутри Питомца: сначала предстоящие (ближайшая первой),
 * затем остальные (новые первыми).
 *
 * @param list<array<string, mixed>> $pets     строки с ключом `id`
 * @param list<array<string, mixed>> $bookings строки с ключами `pet_id`, `status`, `scheduled_at`
 * @return list<array{pet: array<string, mixed>, bookings: list<array<string, mixed>>}>
 */
function bookingsGroupByPet(array $pets, array $bookings, DateTimeImmutable $now): array
{
    $upcoming = [];
    $past = [];
    foreach ($bookings as $booking) {
        $petId = (int) $booking['pet_id'];
        if (bookingIsUpcoming($booking, $now)) {
            $upcoming[$petId][] = $booking;
        } else {
            $past[$petId][] = $booking;
        }
    }

    $byDate = static fn (array $a, array $b): int => strcmp((string) $a['scheduled_at'], (string) $b['scheduled_at']);

    $groups = [];
    foreach ($pets as $pet) {
        $petId = (int) $pet['id'];
        $petUpcoming = $upcoming[$petId] ?? [];
        $petPast = $past[$petId] ?? [];
        usort($petUpcoming, $byDate);
        usort($petPast, static fn (array $a, array $b): int => $byDate($b, $a));

        $groups[] = ['pet' => $pet, 'bookings' => [...$petUpcoming, ...$petPast]];
    }

    return $groups;
}
