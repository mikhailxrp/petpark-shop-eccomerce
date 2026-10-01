<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BookingTest extends TestCase
{
    private const NOW = '2026-10-04 12:00:00'; // воскресенье
    private const MONDAY = '2026-10-05';
    private const SUNDAY = '2026-10-11';

    private const GROOMER = ['work_start' => '10:00:00', 'work_end' => '20:00:00', 'day_off' => null];
    private const VET = ['work_start' => '10:00:00', 'work_end' => '20:00:00', 'day_off' => 0];

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    public function testBlockMinutesAddsBufferOnlyForGrooming(): void
    {
        $this->assertSame(105, \bookingBlockMinutes(90, ['grooming']));
        $this->assertSame(30, \bookingBlockMinutes(30, ['vet']));
        $this->assertSame(75, \bookingBlockMinutes(60, ['vet', 'grooming']));
    }

    public function testBlockMinutesRejectsNonPositiveDuration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        \bookingBlockMinutes(0, ['vet']);
    }

    public function testFreeSlotsCoverWorkdayOnStepGrid(): void
    {
        $slots = \bookingFreeSlots(self::GROOMER, self::MONDAY, 30, [], [], $this->now());

        $this->assertSame('10:00', $slots[0]);
        $this->assertSame('10:30', $slots[1]);
        $this->assertSame('19:30', $slots[array_key_last($slots)]);
        $this->assertCount(20, $slots);
    }

    public function testBookedSlotIsExcluded(): void
    {
        $busy = [['start' => '2026-10-05 11:00:00', 'block_minutes' => 30]];
        $slots = \bookingFreeSlots(self::VET, self::MONDAY, 30, $busy, [], $this->now());

        $this->assertNotContains('11:00', $slots);
        $this->assertContains('10:30', $slots);
        $this->assertContains('11:30', $slots);
    }

    public function testGroomingNinetyMinutesBlocksNextSlotForOneHundredFiveMinutes(): void
    {
        $block = \bookingBlockMinutes(90, ['grooming']);
        $busy = [['start' => '2026-10-05 10:00', 'block_minutes' => $block]];
        $slots = \bookingFreeSlots(self::GROOMER, self::MONDAY, 30, $busy, [], $this->now());

        // 10:00 + 105 минут = 11:45 → первая точка сетки после — 12:00
        $this->assertSame('12:00', $slots[0]);
        $this->assertNotContains('11:30', $slots);
    }

    public function testVetHasNoBufferAfterBooking(): void
    {
        $busy = [['start' => '2026-10-05 10:00:00', 'block_minutes' => \bookingBlockMinutes(60, ['vet'])]];
        $slots = \bookingFreeSlots(self::VET, self::MONDAY, 30, $busy, [], $this->now());

        $this->assertSame('11:00', $slots[0]);
    }

    public function testSlotMustFitBeforeWorkEnd(): void
    {
        $slots = \bookingFreeSlots(self::GROOMER, self::MONDAY, 105, [], [], $this->now());

        // 105 минут до 20:00 → последний старт 18:00 (блок до 19:45)
        $this->assertSame('18:00', $slots[array_key_last($slots)]);
    }

    public function testDateBeyondHorizonHasNoSlots(): void
    {
        $this->assertNotSame([], \bookingFreeSlots(self::GROOMER, '2026-11-03', 30, [], [], $this->now()));
        $this->assertSame([], \bookingFreeSlots(self::GROOMER, '2026-11-04', 30, [], [], $this->now()));
    }

    public function testPastDateHasNoSlots(): void
    {
        $this->assertSame([], \bookingFreeSlots(self::GROOMER, '2026-10-03', 30, [], [], $this->now()));
    }

    public function testTodayOffersOnlyFutureSlots(): void
    {
        $slots = \bookingFreeSlots(self::GROOMER, '2026-10-04', 30, [], [], $this->now());

        $this->assertSame('12:30', $slots[0]);
    }

    public function testVetDayOffHasNoSlots(): void
    {
        $this->assertSame([], \bookingFreeSlots(self::VET, self::SUNDAY, 30, [], [], $this->now()));
        $this->assertNotSame([], \bookingFreeSlots(self::GROOMER, self::SUNDAY, 30, [], [], $this->now()));
    }

    public function testTimeOffPeriodIsExcludedIncludingBounds(): void
    {
        $timeOff = [['date_from' => '2026-10-05', 'date_to' => '2026-10-07']];

        $this->assertSame([], \bookingFreeSlots(self::GROOMER, '2026-10-05', 30, [], $timeOff, $this->now()));
        $this->assertSame([], \bookingFreeSlots(self::GROOMER, '2026-10-07', 30, [], $timeOff, $this->now()));
        $this->assertNotSame([], \bookingFreeSlots(self::GROOMER, '2026-10-08', 30, [], $timeOff, $this->now()));
    }

    public function testBusyBookingOfAnotherDayIsIgnored(): void
    {
        $busy = [['start' => '2026-10-06 10:00:00', 'block_minutes' => 600]];

        $this->assertCount(20, \bookingFreeSlots(self::GROOMER, self::MONDAY, 30, $busy, [], $this->now()));
    }

    public function testInvalidDateIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        \bookingFreeSlots(self::GROOMER, '2026-02-30', 30, [], [], $this->now());
    }

    public function testAllowedStatusTransitions(): void
    {
        $this->assertTrue(\bookingCanTransition('slot_selected', 'confirmed'));
        $this->assertTrue(\bookingCanTransition('slot_selected', 'slot_released'));
        $this->assertTrue(\bookingCanTransition('confirmed', 'cancelled'));
        $this->assertTrue(\bookingCanTransition('confirmed', 'no_show'));
        $this->assertTrue(\bookingCanTransition('confirmed', 'completed'));
    }

    public function testForbiddenStatusTransitionsAreRejected(): void
    {
        $this->assertFalse(\bookingCanTransition('slot_selected', 'completed'));
        $this->assertFalse(\bookingCanTransition('confirmed', 'slot_released'));
        $this->assertFalse(\bookingCanTransition('cancelled', 'confirmed'));
        $this->assertFalse(\bookingCanTransition('completed', 'cancelled'));
        $this->assertFalse(\bookingCanTransition('unknown', 'confirmed'));
    }

    public function testCustomerCanCancelExactlyAtThreshold(): void
    {
        $visit = new DateTimeImmutable('2026-10-05 15:00:00');

        $this->assertTrue(\bookingCanCancelByCustomer($visit, new DateTimeImmutable('2026-10-05 12:00:00'), 3));
        $this->assertTrue(\bookingCanCancelByCustomer($visit, new DateTimeImmutable('2026-10-05 11:00:00'), 3));
    }

    public function testCustomerCannotCancelCloserThanThreshold(): void
    {
        $visit = new DateTimeImmutable('2026-10-05 15:00:00');

        $this->assertFalse(\bookingCanCancelByCustomer($visit, new DateTimeImmutable('2026-10-05 12:00:01'), 3));
        $this->assertFalse(\bookingCanCancelByCustomer($visit, new DateTimeImmutable('2026-10-05 13:00:00'), 3));
    }

    public function testCustomerCannotCancelPastVisit(): void
    {
        $visit = new DateTimeImmutable('2026-10-05 15:00:00');

        $this->assertFalse(\bookingCanCancelByCustomer($visit, new DateTimeImmutable('2026-10-05 16:00:00'), 3));
    }

    public function testWeekBoundsFromMidWeek(): void
    {
        $this->assertSame(['from' => '2026-10-05', 'to' => '2026-10-11'], bookingWeekBounds('2026-10-07'));
    }

    public function testWeekBoundsOnMondayAndSunday(): void
    {
        $this->assertSame(['from' => '2026-10-05', 'to' => '2026-10-11'], bookingWeekBounds(self::MONDAY));
        $this->assertSame(['from' => '2026-10-05', 'to' => '2026-10-11'], bookingWeekBounds(self::SUNDAY));
    }

    public function testWeekBoundsAcrossMonthAndYear(): void
    {
        $this->assertSame(['from' => '2026-12-28', 'to' => '2027-01-03'], bookingWeekBounds('2027-01-01'));
    }

    public function testWeekBoundsRejectInvalidDate(): void
    {
        $this->expectException(InvalidArgumentException::class);

        bookingWeekBounds('2026-02-30');
    }
}
