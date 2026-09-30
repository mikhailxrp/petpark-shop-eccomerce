<?php

declare(strict_types=1);

/**
 * Автоотмена невостребованных Заказов (FR-ORD-007): `ready_for_pickup` /
 * `shipped` дольше ORDER_UNCLAIMED_DAYS суток. Остаток возвращается,
 * оплаченный картой Заказ получает автовозврат денег.
 * Запускать из CLI (cron): php bin/cancel-unclaimed-orders.php
 * Идемпотентно — повторный запуск ничего не меняет.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Доступ только из командной строки: php bin/cancel-unclaimed-orders.php');
}

require_once dirname(__DIR__) . '/config/config.php';
// Автозагрузчик App\* живёт в public/index.php, в CLI его нет — классы подключаем явно
require_once ROOT_PATH . '/src/Services/Payment/PaymentGateway.php';
require_once ROOT_PATH . '/src/Services/Payment/YooMoneyStubGateway.php';
require_once ROOT_PATH . '/src/Services/OrderCancellation.php';

use App\Services\OrderCancellation;
use App\Services\Payment\YooMoneyStubGateway;

$cancellation = new OrderCancellation(new YooMoneyStubGateway(env('PAYMENT_STUB_SECRET')));
$foundIds     = orderFindUnclaimedIds(ORDER_UNCLAIMED_DAYS);
$cancelled    = 0;
$failed       = 0;

foreach ($foundIds as $orderId) {
    try {
        $result = $cancellation->cancel($orderId);

        match ($result) {
            OrderCancellation::RESULT_CANCELLED,
            OrderCancellation::RESULT_REFUNDED => $cancelled++,
            // Заказ отменён, но деньги не возвращены — требует внимания человека
            OrderCancellation::RESULT_REFUND_FAILED => [$cancelled++, $failed++],
            // Статус изменился между выборкой и отменой — не ошибка
            OrderCancellation::RESULT_NOT_ALLOWED => null,
        };
    } catch (Throwable $e) {
        $failed++;
        logException($e, ['order_id' => $orderId]);
    }
}

$summary = sprintf('найдено %d, отменено %d, ошибок %d', count($foundIds), $cancelled, $failed);
$logSummary = $cancelled > 0 || $failed > 0 ? 'logWarning' : 'logInfo';
$logSummary('Автоотмена невостребованных Заказов: ' . $summary);
echo $summary . PHP_EOL;

exit($failed > 0 ? 1 : 0);
