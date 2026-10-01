<?php

declare(strict_types=1);

namespace App\Services\Payment;

use RuntimeException;

/**
 * Заглушка ЮMoney (ADR-001, демо-проект): реального обращения к API нет,
 * «платёжная форма» — страница на нашем сайте. Подпись результата
 * считается по секрету из .env, чтобы callback проверял её так же, как
 * проверял бы подпись реального шлюза, а не верил полю формы на слово.
 */
final class YooMoneyStubGateway implements PaymentGateway
{
    private const PROVIDER = 'yoomoney_stub';
    private const SIGNATURE_ALGO = 'sha256';

    public function __construct(private readonly string $secret)
    {
        if ($secret === '') {
            throw new RuntimeException('Секрет платёжной заглушки не задан (PAYMENT_STUB_SECRET).');
        }
    }

    public function providerName(): string
    {
        return self::PROVIDER;
    }

    public function paymentUrl(int $orderId): string
    {
        return '/payment/' . $orderId;
    }

    public function sign(int $orderId, string $result): string
    {
        return hash_hmac(self::SIGNATURE_ALGO, $orderId . ':' . $result, $this->secret);
    }

    public function verifySignature(int $orderId, string $result, string $signature): bool
    {
        return hash_equals($this->sign($orderId, $result), $signature);
    }

    public function refund(int $orderId, string $amount): bool
    {
        return true;
    }

    public function bookingPaymentUrl(int $bookingId): string
    {
        return '/booking/' . $bookingId . '/pay';
    }

    public function signBooking(int $bookingId, string $result): string
    {
        return hash_hmac(self::SIGNATURE_ALGO, 'booking:' . $bookingId . ':' . $result, $this->secret);
    }

    public function verifyBookingSignature(int $bookingId, string $result, string $signature): bool
    {
        return hash_equals($this->signBooking($bookingId, $result), $signature);
    }

    public function refundBooking(int $bookingId, string $amount): bool
    {
        return true;
    }
}
