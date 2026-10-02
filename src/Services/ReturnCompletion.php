<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Payment\PaymentGateway;

/**
 * Завершение Возврата: деньги, остаток, `completed` и письмо (FR-RET-003).
 * Единственное место, где при Возврате вызывается `refund()`.
 *
 * Порядок обратный `OrderCancellation`: сначала `refund()`, потом запись в
 * БД. Сбой шлюза оставляет Возврат «Одобрен» — можно повторить. Чтобы
 * двойной клик не вернул деньги дважды, строка Возврата блокируется FOR
 * UPDATE на время вызова. Если `refund()` прошёл, а БД упала — Возврат
 * остаётся «Одобрен», факт возврата — в логах (для заглушки риск приемлем).
 */
final class ReturnCompletion
{
    public const RESULT_COMPLETED = 'completed';
    public const RESULT_REFUNDED = 'completed_refunded';
    public const RESULT_CASH_REFUNDED = 'completed_cash_refunded';
    public const RESULT_REFUND_FAILED = 'refund_failed';
    public const RESULT_NOT_ALLOWED = 'not_allowed';
    public const RESULT_ERROR = 'error';

    public function __construct(private readonly PaymentGateway $gateway)
    {
    }

    /** @return string одна из констант RESULT_* */
    public function complete(int $returnId, int $userId): string
    {
        $pdo = getPdo();
        $pdo->beginTransaction();
        $orderId = null;
        $amount = '';
        $refundedAtGateway = false;

        try {
            $return = returnLockForCompletion($returnId);

            if ($return === null || !returnCanTransition((string) $return['status'], 'completed')) {
                $pdo->rollBack();
                return self::RESULT_NOT_ALLOWED;
            }

            $orderId = (int) $return['order_id'];
            $amount = (string) $return['total'];
            $kind = returnRefundKind((string) $return['payment_method'], (string) $return['payment_status']);

            if ($kind === 'card') {
                $refunded = false;

                try {
                    $refunded = $this->gateway->refund($orderId, $amount);
                } catch (\Throwable $e) {
                    logException($e, ['order_id' => $orderId, 'return_id' => $returnId]);
                }

                if (!$refunded) {
                    // Лог — после отката, иначе запись о сбое откатится вместе с транзакцией.
                    $pdo->rollBack();
                    $this->log($orderId, ['action' => 'refund', 'amount' => $amount, 'success' => false]);
                    logError('Возврат денег при Возврате заказа не прошёл', [
                        'order_id' => $orderId, 'return_id' => $returnId, 'amount' => $amount,
                    ]);
                    return self::RESULT_REFUND_FAILED;
                }

                $refundedAtGateway = true;
            }

            if (!returnComplete($returnId, $orderId, $userId, $kind !== 'none')) {
                $pdo->rollBack();
                return self::RESULT_NOT_ALLOWED;
            }

            if ($kind === 'card') {
                $this->log($orderId, ['action' => 'refund', 'amount' => $amount, 'success' => true]);
            } elseif ($kind === 'cash') {
                $this->log($orderId, ['action' => 'cash_refund_marked', 'amount' => $amount, 'success' => true]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            logException($e, ['return_id' => $returnId]);

            if ($refundedAtGateway && $orderId !== null) {
                logError('Деньги по Возврату возвращены, но завершение не записано в БД', [
                    'order_id' => $orderId, 'return_id' => $returnId, 'amount' => $amount,
                ]);
                try {
                    $this->log($orderId, ['action' => 'refund', 'amount' => $amount, 'success' => true, 'db_failed' => true]);
                } catch (\Throwable $logError) {
                    logException($logError, ['order_id' => $orderId]);
                }
            }

            return self::RESULT_ERROR;
        }

        return match ($kind) {
            'card'  => self::RESULT_REFUNDED,
            'cash'  => self::RESULT_CASH_REFUNDED,
            default => self::RESULT_COMPLETED,
        };
    }

    /** @param array<string, mixed> $payload */
    private function log(int $orderId, array $payload): void
    {
        orderPaymentLogCreate(
            $orderId,
            $this->gateway->providerName(),
            true,
            (string) json_encode($payload, JSON_UNESCAPED_UNICODE)
        );
    }
}
