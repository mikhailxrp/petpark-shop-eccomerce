<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Заглушка AmoCRM (ADR-001, ADR-023, демо-проект): реального API нет.
 * Контракт `pushOrder()` / `updateDeal()` сохранён для будущей реальной
 * интеграции — вызывающий код при её подключении не меняется.
 * Сам `amocrm_id` в БД пишет вызывающий код (orderSetAmoCrm()).
 */
final class AmoCrm
{
    private const DEAL_ID_PREFIX = 'DEMO-';

    /** Создать сделку по Заказу; возвращает её идентификатор в AmoCRM. */
    public function pushOrder(int $orderId): string
    {
        $dealId = self::DEAL_ID_PREFIX . $orderId;

        logInfo('AmoCRM (демо): сделка создана', ['order_id' => $orderId, 'amocrm_id' => $dealId]);

        return $dealId;
    }

    /** Обновить сделку после смены статуса или правки Заказа. */
    public function updateDeal(string $dealId, string $status, ?string $total = null): bool
    {
        logInfo('AmoCRM (демо): сделка обновлена', ['amocrm_id' => $dealId, 'status' => $status, 'total' => $total]);

        return true;
    }
}
