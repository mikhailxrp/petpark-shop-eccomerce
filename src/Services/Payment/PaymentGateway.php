<?php

declare(strict_types=1);

namespace App\Services\Payment;

/**
 * Контракт платёжного провайдера (php.md, «Functions vs classes»):
 * контроллеры знают только этот интерфейс, реальный шлюз подменит
 * заглушку без правок PaymentController.
 */
interface PaymentGateway
{
    public const RESULT_PAID = 'paid';
    public const RESULT_DECLINED = 'declined';

    public function providerName(): string;

    /** Куда отправить Покупателя, чтобы он оплатил Заказ. */
    public function paymentUrl(int $orderId): string;

    /** Подпись результата оплаты — её провайдер присылает вместе с callback. */
    public function sign(int $orderId, string $result): string;

    public function verifySignature(int $orderId, string $result, string $signature): bool;

    /** Возврат средств. В Фазе 2 вызывающего кода нет (FR-PAY-005 — Фаза 3). */
    public function refund(int $orderId, string $amount): bool;

    /** Куда отправить Покупателя, чтобы он внёс Депозит за Запись (FR-SV-005). */
    public function bookingPaymentUrl(int $bookingId): string;

    /** Подпись результата оплаты Депозита; не совпадает с подписью Заказа с тем же id. */
    public function signBooking(int $bookingId, string $result): string;

    public function verifyBookingSignature(int $bookingId, string $result, string $signature): bool;

    /** Возврат Депозита. Вызывающего кода пока нет (отмена Записи — Таск 6). */
    public function refundBooking(int $bookingId, string $amount): bool;
}
