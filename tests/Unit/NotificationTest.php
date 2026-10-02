<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class NotificationTest extends TestCase
{
    public function testDelayBeforeEachAttemptGrowsFromZero(): void
    {
        $this->assertSame(0, notificationDelayBeforeAttempt(1));
        $this->assertSame(1, notificationDelayBeforeAttempt(2));
        $this->assertSame(5, notificationDelayBeforeAttempt(3));
        $this->assertSame(15, notificationDelayBeforeAttempt(4));
        $this->assertSame(60, notificationDelayBeforeAttempt(5));
    }

    public function testEveryAttemptHasADelay(): void
    {
        $this->assertCount(NOTIFICATION_MAX_ATTEMPTS, NOTIFICATION_DELAY_BEFORE_ATTEMPT);
    }

    public function testAttemptOutOfRangeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        notificationDelayBeforeAttempt(NOTIFICATION_MAX_ATTEMPTS + 1);
    }

    public function testFailureSchedulesNextAttemptWithItsDelay(): void
    {
        $this->assertSame(1, notificationRetryDelayAfterFailure(1));
        $this->assertSame(5, notificationRetryDelayAfterFailure(2));
        $this->assertSame(15, notificationRetryDelayAfterFailure(3));
        $this->assertSame(60, notificationRetryDelayAfterFailure(4));
    }

    public function testEveryOrderStatusHasAMessage(): void
    {
        foreach (array_keys(ORDER_STATUS_TRANSITIONS) as $status) {
            $message = notificationOrderStatusMessage($status, 'unpaid');
            $this->assertNotNull($message, $status);
            $this->assertNotSame('', $message['subject'], $status);
        }
    }

    public function testUnknownOrderStatusHasNoMessage(): void
    {
        $this->assertNull(notificationOrderStatusMessage('archived', 'unpaid'));
    }

    public function testRefundNoteOnlyForCancelledPaidOrder(): void
    {
        $this->assertTrue(notificationOrderStatusMessage('cancelled', 'paid')['refund_note']);
        $this->assertFalse(notificationOrderStatusMessage('cancelled', 'unpaid')['refund_note']);
        $this->assertFalse(notificationOrderStatusMessage('shipped', 'paid')['refund_note']);
    }

    private function reminderDue(string $status, string $scheduled, string $created): bool
    {
        return notificationBookingReminderDue(
            $status,
            new DateTimeImmutable($scheduled),
            new DateTimeImmutable($created),
            new DateTimeImmutable('2026-10-10 12:00:00')
        );
    }

    public function testReminderForVisitIn2h50(): void
    {
        $this->assertTrue($this->reminderDue('confirmed', '2026-10-10 14:50:00', '2026-10-01 10:00:00'));
    }

    public function testNoReminderForVisitIn3h10(): void
    {
        $this->assertFalse($this->reminderDue('confirmed', '2026-10-10 15:10:00', '2026-10-01 10:00:00'));
    }

    public function testNoReminderForBookingCreated2hBeforeVisit(): void
    {
        $this->assertFalse($this->reminderDue('confirmed', '2026-10-10 14:50:00', '2026-10-10 12:50:00'));
    }

    public function testNoReminderForNotConfirmedOrPastVisit(): void
    {
        $this->assertFalse($this->reminderDue('cancelled', '2026-10-10 14:50:00', '2026-10-01 10:00:00'));
        $this->assertFalse($this->reminderDue('confirmed', '2026-10-10 11:00:00', '2026-10-01 10:00:00'));
    }

    public function testFifthFailureMarksFailed(): void
    {
        $this->assertNull(notificationRetryDelayAfterFailure(5));
        $this->assertNull(notificationRetryDelayAfterFailure(6));
    }
}
