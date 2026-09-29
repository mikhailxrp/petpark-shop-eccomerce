<?php

declare(strict_types=1);

/**
 * Отмена Заказов с истёкшим резервом (BR-003).
 * Запускать из CLI (cron): php bin/expire-reservations.php
 * Идемпотентно — повторный запуск ничего не меняет.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Доступ только из командной строки: php bin/expire-reservations.php');
}

require_once dirname(__DIR__) . '/config/config.php';

$expiredIds = orderFindExpiredIds();
$cancelled  = 0;
$failed     = 0;

foreach ($expiredIds as $orderId) {
    try {
        if (orderTransition($orderId, 'cancelled')) {
            $cancelled++;
        }
    } catch (Throwable $e) {
        $failed++;
        logException($e, ['order_id' => $orderId]);
    }
}

$summary = sprintf('найдено %d, отменено %d, ошибок %d', count($expiredIds), $cancelled, $failed);
// Прогон, что-то отменивший или упавший, виден при любом APP_LOG_LEVEL ≤ warning;
// пустой прогон — обычный info.
$logSummary = $cancelled > 0 || $failed > 0 ? 'logWarning' : 'logInfo';
$logSummary('Истечение резерва: ' . $summary);
echo $summary . PHP_EOL;

exit($failed > 0 ? 1 : 0);
