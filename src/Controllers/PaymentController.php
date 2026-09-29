<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Payment\PaymentGateway;
use App\Services\Payment\YooMoneyStubGateway;

/**
 * Оплата картой на сайте — заглушка ЮMoney (phase-2.md, Таск 6;
 * FR-PAY-001, FR-PAY-003, FR-PAY-004). Страницы доступны только сессии,
 * оформившей Заказ, либо его владельцу (тот же критерий, что у страницы
 * успеха, orderCanBeViewedBySession()); чужой/несуществующий Заказ — 404.
 *
 * Смена статуса — только через orderTransition(); callback пишет
 * payment_logs при каждом вызове. Повторный «оплачено» — не ошибка:
 * orderTransition() вернёт false, Заказ уже подтверждён (FR-PAY-003).
 * Пользователю не показываем ничего про подпись и шлюз.
 */
final class PaymentController
{
    private const PAYMENT_FAILED_ERROR = 'Не удалось выполнить платёж. Попробуйте ещё раз.';
    private const ORDER_CANCELLED_NOTICE = 'Заказ отменён. Резерв товаров снят.';

    private PaymentGateway $gateway;

    public function __construct()
    {
        $this->gateway = new YooMoneyStubGateway(env('PAYMENT_STUB_SECRET'));
    }

    public function show(string $id): void
    {
        $order = $this->findPayableOrder($id);

        render('payment/gateway-stub', [
            'order'           => $order,
            'signaturePaid'   => $this->gateway->sign((int) $order['id'], PaymentGateway::RESULT_PAID),
            'signatureDeclined' => $this->gateway->sign((int) $order['id'], PaymentGateway::RESULT_DECLINED),
        ]);
    }

    public function callback(string $id): void
    {
        requireCsrf();

        $order = $this->findViewableOrder($id);
        $orderId = (int) $order['id'];

        $result = (string) input('result');
        $signature = (string) input('signature');
        $signatureValid = in_array($result, [PaymentGateway::RESULT_PAID, PaymentGateway::RESULT_DECLINED], true)
            && $this->gateway->verifySignature($orderId, $result, $signature);

        orderPaymentLogCreate(
            $orderId,
            $this->gateway->providerName(),
            $signatureValid,
            (string) json_encode(['result' => $result, 'signature' => $signature], JSON_UNESCAPED_UNICODE)
        );

        if (!$signatureValid || $order['payment_method'] !== 'card_online') {
            logWarning('Платёжный callback отклонён', ['order_id' => $orderId, 'signature_valid' => $signatureValid]);
            setFlash('payment_error', self::PAYMENT_FAILED_ERROR);
            redirect('/payment/' . $orderId . '/failed');
        }

        if ($result === PaymentGateway::RESULT_PAID) {
            // false = уже подтверждён/отменён: повторное уведомление игнорируем.
            orderTransition($orderId, 'confirmed', 'paid');
            redirect('/checkout/success/' . $orderId);
        }

        redirect('/payment/' . $orderId . '/failed');
    }

    public function failed(string $id): void
    {
        $order = $this->findPayableOrder($id);

        render('payment/failed', [
            'order' => $order,
            'error' => getFlash('payment_error'),
        ]);
    }

    public function cancel(string $id): void
    {
        requireCsrf();

        $order = $this->findViewableOrder($id);
        $orderId = (int) $order['id'];

        if ($order['payment_method'] === 'card_online' && $order['payment_status'] === 'unpaid') {
            orderTransition($orderId, 'cancelled');
            setFlash('cart_notice', self::ORDER_CANCELLED_NOTICE);
            redirect('/cart');
        }

        redirect('/checkout/success/' . $orderId);
    }

    /**
     * Заказ доступен сессии; иначе 404 (не раскрываем, существует ли номер).
     *
     * @return array<string, mixed>
     */
    private function findViewableOrder(string $id): array
    {
        $orderId = ctype_digit($id) ? (int) $id : 0;
        $order = $orderId > 0 ? orderFindById($orderId) : null;

        if ($order === null || !orderCanBeViewedBySession($order)) {
            http_response_code(404);
            render('errors/404');
            exit;
        }

        return $order;
    }

    /**
     * Заказ, который ещё можно оплатить; уже оплаченный/отменённый или
     * «при получении» — на страницу успеха.
     *
     * @return array<string, mixed>
     */
    private function findPayableOrder(string $id): array
    {
        $order = $this->findViewableOrder($id);

        $payable = $order['payment_method'] === 'card_online'
            && $order['payment_status'] === 'unpaid'
            && $order['status'] === 'new';

        if (!$payable) {
            redirect('/checkout/success/' . (int) $order['id']);
        }

        return $order;
    }
}
