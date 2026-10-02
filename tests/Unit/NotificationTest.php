<?php

declare(strict_types=1);

namespace Tests\Unit;

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

    public function testFifthFailureMarksFailed(): void
    {
        $this->assertNull(notificationRetryDelayAfterFailure(5));
        $this->assertNull(notificationRetryDelayAfterFailure(6));
    }
}
