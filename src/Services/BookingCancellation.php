<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Payment\PaymentGateway;

/**
 * Отмена подтверждённой Записи с возвратом Депозита (FR-SV-008). Единая
 * точка для отмены Покупателем (Таск 6) и персоналом (Таски 7–8). Порог в
 * 3 часа здесь НЕ проверяется: его применяет вызывающий код для Покупателя
 * (`bookingCanCancelByCustomer()`), форс-мажорная отмена персонала идёт без него.
 *
 * Порядок как у `OrderCancellation`: сначала переход в `cancelled`, затем
 * `refundBooking()`. Если возврат не прошёл — Запись остаётся отменённой с
 * `deposit_status = held`: лучше отменённая Запись с невозвращённым
 * Депозитом, о котором сказано в логе, чем возврат за не отменённую.
 */
final class BookingCancellation
{
    public const RESULT_CANCELLED = 'cancelled';
    public const RESULT_REFUNDED = 'cancelled_refunded';
    public const RESULT_REFUND_FAILED = 'cancelled_refund_failed';
    public const RESULT_NOT_ALLOWED = 'not_allowed';

    public function __construct(private readonly PaymentGateway $gateway)
    {
    }

    /** @return string одна из констант RESULT_* */
    public function cancel(int $bookingId): string
    {
        $booking = bookingFindById($bookingId);

        if ($booking === null || !bookingTransition($bookingId, 'cancelled')) {
            return self::RESULT_NOT_ALLOWED;
        }

        if ($booking['deposit_status'] !== 'held') {
            return self::RESULT_CANCELLED;
        }

        $amount = (string) $booking['deposit_amount'];
        $refunded = false;

        try {
            $refunded = $this->gateway->refundBooking($bookingId, $amount);
        } catch (\Throwable $e) {
            logException($e, ['booking_id' => $bookingId]);
        }

        bookingPaymentLogCreate(
            $bookingId,
            $this->gateway->providerName(),
            true,
            (string) json_encode(
                ['action' => 'refund', 'amount' => $amount, 'success' => $refunded],
                JSON_UNESCAPED_UNICODE
            )
        );

        if (!$refunded) {
            logError('Возврат Депозита при отмене Записи не прошёл', ['booking_id' => $bookingId, 'amount' => $amount]);
            return self::RESULT_REFUND_FAILED;
        }

        bookingMarkDepositReturned($bookingId);

        return self::RESULT_REFUNDED;
    }
}
