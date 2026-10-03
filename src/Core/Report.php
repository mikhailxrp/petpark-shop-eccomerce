<?php

declare(strict_types=1);

/**
 * Отчёты Владельца — /admin/reports (phase-7.md, Таск 10; FR-ADM-004).
 * Чистая логика без БД: границы периода, валидация GET-параметров,
 * разбивка Услуг по виду. Деньги — строки DECIMAL / целые копейки, не float.
 */

const REPORT_PERIOD_DAY = 'day';
const REPORT_PERIOD_WEEK = 'week';
const REPORT_PERIOD_MONTH = 'month';
const REPORT_PERIOD_DEFAULT = REPORT_PERIOD_WEEK;
const REPORT_PERIODS = [REPORT_PERIOD_DAY, REPORT_PERIOD_WEEK, REPORT_PERIOD_MONTH];

const REPORT_DATE_FORMAT = 'Y-m-d';
const REPORT_DATETIME_FORMAT = 'Y-m-d H:i:s';
const REPORT_TOP_PRODUCTS_LIMIT = 10;

const REPORT_KIND_OTHER = 'other';
const REPORT_KIND_LABELS = [
    'grooming'        => 'Груминг',
    'vet'             => 'Ветеринария',
    REPORT_KIND_OTHER => 'Другие (Услуга удалена из прайса)',
];

/** Неизвестный или нестроковый `period` → период по умолчанию. */
function reportNormalizePeriod(mixed $period): string
{
    return is_string($period) && in_array($period, REPORT_PERIODS, true)
        ? $period
        : REPORT_PERIOD_DEFAULT;
}

/**
 * Опорная дата из GET: строго `Y-m-d` существующего дня, иначе сегодня.
 * `2026-02-30` не «переезжает» на март, а считается неверной.
 */
function reportNormalizeDate(mixed $date, DateTimeImmutable $today): DateTimeImmutable
{
    if (is_string($date)) {
        $parsed = DateTimeImmutable::createFromFormat('!' . REPORT_DATE_FORMAT, $date);
        $errors = DateTimeImmutable::getLastErrors();

        if ($parsed !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
            return $parsed;
        }
    }

    return $today->setTime(0, 0);
}

/**
 * Границы периода как полуинтервал [from, to): полночь `to` уже принадлежит
 * следующему периоду, поэтому Заказ на стыке попадает ровно в один период.
 * Неделя — с понедельника.
 *
 * @return array{from: DateTimeImmutable, to: DateTimeImmutable}
 */
function reportBounds(string $period, DateTimeImmutable $date): array
{
    $day = $date->setTime(0, 0);

    if ($period === REPORT_PERIOD_DAY) {
        return ['from' => $day, 'to' => $day->modify('+1 day')];
    }

    if ($period === REPORT_PERIOD_WEEK) {
        $monday = $day->modify('-' . ((int) $day->format('N') - 1) . ' days');

        return ['from' => $monday, 'to' => $monday->modify('+7 days')];
    }

    if ($period === REPORT_PERIOD_MONTH) {
        $first = $day->modify('first day of this month');

        return ['from' => $first, 'to' => $first->modify('+1 month')];
    }

    throw new InvalidArgumentException("Неизвестный период: {$period}");
}

/**
 * Все даты периода по порядку (`Y-m-d`) — ось графика без пропусков.
 *
 * @param array{from: DateTimeImmutable, to: DateTimeImmutable} $bounds
 * @return list<string>
 */
function reportDays(array $bounds): array
{
    $days = [];
    for ($day = $bounds['from']; $day < $bounds['to']; $day = $day->modify('+1 day')) {
        $days[] = $day->format(REPORT_DATE_FORMAT);
    }

    return $days;
}

/**
 * Дополняет строки «по дням» из БД нулевыми днями и считает итог.
 *
 * @param list<string> $days
 * @param array<int, array{day: string, orders_count: int|string, revenue: string}> $rows
 * @return array{days: list<array{day: string, orders_count: int, revenue: string}>, orders_count: int, revenue: string}
 */
function reportRevenueSeries(array $days, array $rows): array
{
    $byDay = [];
    foreach ($rows as $row) {
        $byDay[(string) $row['day']] = $row;
    }

    $series = [];
    $totalOrders = 0;
    $totalKopecks = 0;

    foreach ($days as $day) {
        $count = (int) ($byDay[$day]['orders_count'] ?? 0);
        $revenue = (string) ($byDay[$day]['revenue'] ?? '0.00');

        $series[] = ['day' => $day, 'orders_count' => $count, 'revenue' => $revenue];
        $totalOrders += $count;
        $totalKopecks += orderMoneyToKopecks($revenue);
    }

    return [
        'days'         => $series,
        'orders_count' => $totalOrders,
        'revenue'      => orderKopecksToMoney($totalKopecks),
    ];
}

/**
 * Услуги по виду: груминг и ветеринария всегда присутствуют (пусто → нули),
 * Услуга, удалённая из прайса (`service_id` NULL), уходит в «Другие» —
 * эта строка показывается, только когда она не пуста.
 *
 * @param array<int, array{kind: string|null, bookings_count: int|string, services_count: int|string, revenue: string}> $rows
 * @return array{kinds: list<array{kind: string, label: string, bookings_count: int, services_count: int, revenue: string}>, bookings_count: int, services_count: int, revenue: string}
 */
function reportServicesBreakdown(array $rows): array
{
    $byKind = [];
    foreach ($rows as $row) {
        $kind = isset(REPORT_KIND_LABELS[(string) $row['kind']]) ? (string) $row['kind'] : REPORT_KIND_OTHER;
        $byKind[$kind] = [
            'bookings_count' => ($byKind[$kind]['bookings_count'] ?? 0) + (int) $row['bookings_count'],
            'services_count' => ($byKind[$kind]['services_count'] ?? 0) + (int) $row['services_count'],
            'kopecks'        => ($byKind[$kind]['kopecks'] ?? 0) + orderMoneyToKopecks((string) $row['revenue']),
        ];
    }

    $kinds = [];
    $bookingsTotal = 0;
    $servicesTotal = 0;
    $kopecksTotal = 0;

    foreach (REPORT_KIND_LABELS as $kind => $label) {
        if ($kind === REPORT_KIND_OTHER && !isset($byKind[$kind])) {
            continue;
        }

        $entry = $byKind[$kind] ?? ['bookings_count' => 0, 'services_count' => 0, 'kopecks' => 0];

        $kinds[] = [
            'kind'           => $kind,
            'label'          => $label,
            'bookings_count' => $entry['bookings_count'],
            'services_count' => $entry['services_count'],
            'revenue'        => orderKopecksToMoney($entry['kopecks']),
        ];
        $bookingsTotal += $entry['bookings_count'];
        $servicesTotal += $entry['services_count'];
        $kopecksTotal += $entry['kopecks'];
    }

    return [
        'kinds'          => $kinds,
        'bookings_count' => $bookingsTotal,
        'services_count' => $servicesTotal,
        'revenue'        => orderKopecksToMoney($kopecksTotal),
    ];
}

/**
 * Подпись периода для заголовка: «05.10.2026», «05.10 – 11.10.2026», «10.2026».
 *
 * @param array{from: DateTimeImmutable, to: DateTimeImmutable} $bounds
 */
function reportPeriodLabel(string $period, array $bounds): string
{
    $last = $bounds['to']->modify('-1 day');

    return match ($period) {
        REPORT_PERIOD_DAY  => $bounds['from']->format('d.m.Y'),
        REPORT_PERIOD_WEEK => $bounds['from']->format('d.m') . ' – ' . $last->format('d.m.Y'),
        default            => $bounds['from']->format('m.Y'),
    };
}
