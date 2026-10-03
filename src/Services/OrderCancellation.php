<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Payment\PaymentGateway;

/**
 * Отмена Заказа с возвратом остатка и, если Заказ оплачен картой на сайте,
 * денег (FR-PAY-005, FR-STOCK-004). Единая точка для ручной отмены в
 * карточке Заказа и автоотмены по cron (phase-3.md, Таск 6).
 *
 * Порядок: сначала переход в `cancelled` (транзакция, остаток вернулся),
 * затем `refund()`. Если возврат не прошёл — Заказ остаётся отменённым с
 * `payment_status = paid`: лучше отменённый Заказ с невозвращёнными
 * деньгами, о котором сказано в логе, чем возврат денег за не отменённый.
 */
final class OrderCancellation
{
    public const RESULT_CANCELLED = 'cancelled';
    public const RESULT_REFUNDED = 'cancelled_refunded';
    public const RESULT_REFUND_FAILED = 'cancelled_refund_failed';
    public const RESULT_NOT_ALLOWED = 'not_allowed';

    public function __construct(private readonly PaymentGateway $gateway)
    {
    }

    /**
     * @param list<string>|null $allowedFromStatuses допустимые текущие статусы (проверка под
     *                                               блокировкой); null — любые по карте переходов
     * @return string одна из констант RESULT_*
     */
    public function cancel(int $orderId, ?array $allowedFromStatuses = null): string
    {
        $order = orderFindById($orderId);

        if ($order === null || !orderTransition($orderId, 'cancelled', null, $allowedFromStatuses)) {
            return self::RESULT_NOT_ALLOWED;
        }

        $needsRefund = $order['payment_status'] === 'paid' && $order['payment_method'] === 'card_online';

        if (!$needsRefund) {
            return self::RESULT_CANCELLED;
        }

        $amount = (string) $order['total'];
        $refunded = false;

        try {
            $refunded = $this->gateway->refund($orderId, $amount);
        } catch (\Throwable $e) {
            logException($e, ['order_id' => $orderId]);
        }

        orderPaymentLogCreate(
            $orderId,
            $this->gateway->providerName(),
            true,
            (string) json_encode(
                ['action' => 'refund', 'amount' => $amount, 'success' => $refunded],
                JSON_UNESCAPED_UNICODE
            )
        );

        if (!$refunded) {
            logError('Возврат денег при отмене Заказа не прошёл', ['order_id' => $orderId, 'amount' => $amount]);
            return self::RESULT_REFUND_FAILED;
        }

        orderMarkRefunded($orderId);

        return self::RESULT_REFUNDED;
    }
}
