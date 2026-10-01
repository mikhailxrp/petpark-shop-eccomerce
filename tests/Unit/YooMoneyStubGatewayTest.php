<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Payment\PaymentGateway;
use App\Services\Payment\YooMoneyStubGateway;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once ROOT_PATH . '/src/Services/Payment/PaymentGateway.php';
require_once ROOT_PATH . '/src/Services/Payment/YooMoneyStubGateway.php';

final class YooMoneyStubGatewayTest extends TestCase
{
    public function testValidSignatureIsAccepted(): void
    {
        $gateway = new YooMoneyStubGateway('secret');
        $signature = $gateway->sign(42, PaymentGateway::RESULT_PAID);

        $this->assertTrue($gateway->verifySignature(42, PaymentGateway::RESULT_PAID, $signature));
    }

    public function testSignatureIsBoundToOrderAndResult(): void
    {
        $gateway = new YooMoneyStubGateway('secret');
        $signature = $gateway->sign(42, PaymentGateway::RESULT_DECLINED);

        $this->assertFalse($gateway->verifySignature(42, PaymentGateway::RESULT_PAID, $signature));
        $this->assertFalse($gateway->verifySignature(43, PaymentGateway::RESULT_DECLINED, $signature));
    }

    public function testSignatureFromOtherSecretIsRejected(): void
    {
        $forged = (new YooMoneyStubGateway('other'))->sign(42, PaymentGateway::RESULT_PAID);

        $this->assertFalse((new YooMoneyStubGateway('secret'))->verifySignature(42, PaymentGateway::RESULT_PAID, $forged));
        $this->assertFalse((new YooMoneyStubGateway('secret'))->verifySignature(42, PaymentGateway::RESULT_PAID, ''));
    }

    public function testEmptySecretIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        new YooMoneyStubGateway('');
    }

    public function testPaymentUrlPointsToLocalStubPage(): void
    {
        $this->assertSame('/payment/7', (new YooMoneyStubGateway('secret'))->paymentUrl(7));
    }

    public function testBookingSignatureIsAcceptedAndBoundToBookingAndResult(): void
    {
        $gateway = new YooMoneyStubGateway('secret');
        $signature = $gateway->signBooking(5, PaymentGateway::RESULT_PAID);

        $this->assertTrue($gateway->verifyBookingSignature(5, PaymentGateway::RESULT_PAID, $signature));
        $this->assertFalse($gateway->verifyBookingSignature(5, PaymentGateway::RESULT_DECLINED, $signature));
        $this->assertFalse($gateway->verifyBookingSignature(6, PaymentGateway::RESULT_PAID, $signature));
        $this->assertFalse($gateway->verifyBookingSignature(5, PaymentGateway::RESULT_PAID, ''));
    }

    public function testBookingSignatureDiffersFromOrderSignatureWithSameId(): void
    {
        $gateway = new YooMoneyStubGateway('secret');

        $this->assertNotSame(
            $gateway->sign(5, PaymentGateway::RESULT_PAID),
            $gateway->signBooking(5, PaymentGateway::RESULT_PAID)
        );
        $this->assertFalse($gateway->verifySignature(5, PaymentGateway::RESULT_PAID, $gateway->signBooking(5, PaymentGateway::RESULT_PAID)));
        $this->assertFalse($gateway->verifyBookingSignature(5, PaymentGateway::RESULT_PAID, $gateway->sign(5, PaymentGateway::RESULT_PAID)));
    }

    public function testBookingPaymentUrlPointsToLocalStubPage(): void
    {
        $this->assertSame('/booking/7/pay', (new YooMoneyStubGateway('secret'))->bookingPaymentUrl(7));
    }
}
