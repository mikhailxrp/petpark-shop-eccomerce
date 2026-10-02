<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ReportTest extends TestCase
{
    private const TODAY = '2026-10-07 15:30:00'; // среда

    private function bounds(string $period, string $date): array
    {
        $b = \reportBounds($period, new DateTimeImmutable($date));

        return [$b['from']->format('Y-m-d H:i:s'), $b['to']->format('Y-m-d H:i:s')];
    }

    public function testDayBoundsAreHalfOpenInterval(): void
    {
        $this->assertSame(['2026-10-07 00:00:00', '2026-10-08 00:00:00'], $this->bounds('day', self::TODAY));
    }

    public function testWeekStartsOnMondayAndSundayBelongsToSameWeek(): void
    {
        $expected = ['2026-10-05 00:00:00', '2026-10-12 00:00:00'];

        $this->assertSame($expected, $this->bounds('week', '2026-10-05'));
        $this->assertSame($expected, $this->bounds('week', self::TODAY));
        $this->assertSame($expected, $this->bounds('week', '2026-10-11 23:59:59'));
        $this->assertSame(['2026-10-12 00:00:00', '2026-10-19 00:00:00'], $this->bounds('week', '2026-10-12'));
    }

    public function testWeekCrossesMonthAndYearBoundary(): void
    {
        $this->assertSame(['2026-12-28 00:00:00', '2027-01-04 00:00:00'], $this->bounds('week', '2027-01-01'));
        $this->assertSame(['2026-09-28 00:00:00', '2026-10-05 00:00:00'], $this->bounds('week', '2026-10-01'));
    }

    public function testMonthBoundsIncludingFebruaryAndDecember(): void
    {
        $this->assertSame(['2026-10-01 00:00:00', '2026-11-01 00:00:00'], $this->bounds('month', self::TODAY));
        $this->assertSame(['2028-02-01 00:00:00', '2028-03-01 00:00:00'], $this->bounds('month', '2028-02-29'));
        $this->assertSame(['2026-12-01 00:00:00', '2027-01-01 00:00:00'], $this->bounds('month', '2026-12-31'));
    }

    public function testBoundsRejectUnknownPeriod(): void
    {
        $this->expectException(InvalidArgumentException::class);
        \reportBounds('year', new DateTimeImmutable(self::TODAY));
    }

    public function testNormalizePeriodFallsBackToDefault(): void
    {
        $this->assertSame('day', \reportNormalizePeriod('day'));
        $this->assertSame('month', \reportNormalizePeriod('month'));

        foreach (['year', '', null, ['day'], 5, 'DAY'] as $bad) {
            $this->assertSame(REPORT_PERIOD_DEFAULT, \reportNormalizePeriod($bad));
        }
    }

    public function testNormalizeDateFallsBackToToday(): void
    {
        $today = new DateTimeImmutable(self::TODAY);

        $this->assertSame('2026-02-28', \reportNormalizeDate('2026-02-28', $today)->format('Y-m-d'));

        foreach (['2026-02-30', '2026-13-01', 'abc', '', '07.10.2026', '2026-10-7', null, ['x']] as $bad) {
            $this->assertSame('2026-10-07 00:00:00', \reportNormalizeDate($bad, $today)->format('Y-m-d H:i:s'));
        }
    }

    public function testDaysListCoversPeriodWithoutGaps(): void
    {
        $days = \reportDays(\reportBounds('week', new DateTimeImmutable('2027-01-01')));

        $this->assertSame(
            ['2026-12-28', '2026-12-29', '2026-12-30', '2026-12-31', '2027-01-01', '2027-01-02', '2027-01-03'],
            $days
        );
        $this->assertCount(31, \reportDays(\reportBounds('month', new DateTimeImmutable('2026-10-15'))));
    }

    public function testRevenueSeriesFillsEmptyDaysAndSumsWithoutFloat(): void
    {
        $series = \reportRevenueSeries(
            ['2026-10-05', '2026-10-06', '2026-10-07'],
            [
                ['day' => '2026-10-05', 'orders_count' => '2', 'revenue' => '0.10'],
                ['day' => '2026-10-07', 'orders_count' => '1', 'revenue' => '0.20'],
            ]
        );

        $this->assertSame(3, $series['orders_count']);
        $this->assertSame('0.30', $series['revenue']);
        $this->assertSame(0, $series['days'][1]['orders_count']);
        $this->assertSame('0.00', $series['days'][1]['revenue']);
    }

    public function testRevenueSeriesForEmptyPeriodIsZero(): void
    {
        $series = \reportRevenueSeries(['2026-10-07'], []);

        $this->assertSame(0, $series['orders_count']);
        $this->assertSame('0.00', $series['revenue']);
    }

    public function testServicesBreakdownSplitsKindsAndKeepsEmptyOnes(): void
    {
        $result = \reportServicesBreakdown([
            ['kind' => 'vet', 'bookings_count' => '2', 'services_count' => '3', 'revenue' => '2500.50'],
            ['kind' => 'grooming', 'bookings_count' => '1', 'services_count' => '1', 'revenue' => '1999.50'],
        ]);

        $this->assertSame(['grooming', 'vet'], array_column($result['kinds'], 'kind'));
        $this->assertSame('1999.50', $result['kinds'][0]['revenue']);
        $this->assertSame(3, $result['bookings_count']);
        $this->assertSame(4, $result['services_count']);
        $this->assertSame('4500.00', $result['revenue']);
    }

    public function testServicesBreakdownOfEmptyPeriodHasZeroKindsAndNoOtherRow(): void
    {
        $result = \reportServicesBreakdown([]);

        $this->assertSame(['grooming', 'vet'], array_column($result['kinds'], 'kind'));
        $this->assertSame(0, $result['bookings_count']);
        $this->assertSame('0.00', $result['revenue']);
    }

    public function testServicesBreakdownPutsDeletedServicesIntoOther(): void
    {
        $result = \reportServicesBreakdown([
            ['kind' => null, 'bookings_count' => '1', 'services_count' => '1', 'revenue' => '500.00'],
        ]);

        $this->assertSame(['grooming', 'vet', 'other'], array_column($result['kinds'], 'kind'));
        $this->assertSame('500.00', $result['kinds'][2]['revenue']);
    }

    public function testPeriodLabel(): void
    {
        $date = new DateTimeImmutable('2026-10-07');

        $this->assertSame('07.10.2026', \reportPeriodLabel('day', \reportBounds('day', $date)));
        $this->assertSame('05.10 – 11.10.2026', \reportPeriodLabel('week', \reportBounds('week', $date)));
        $this->assertSame('10.2026', \reportPeriodLabel('month', \reportBounds('month', $date)));
    }
}
