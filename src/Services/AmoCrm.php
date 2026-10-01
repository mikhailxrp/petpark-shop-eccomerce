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
    private const BOOKING_DEAL_ID_PREFIX = 'DEMO-B';

    /** Создать сделку по Заказу; возвращает её идентификатор в AmoCRM. */
    public function pushOrder(int $orderId): string
    {
        $dealId = self::DEAL_ID_PREFIX . $orderId;

        logInfo('AmoCRM (демо): сделка создана', ['order_id' => $orderId, 'amocrm_id' => $dealId]);

        return $dealId;
    }

    /**
     * Создать сделку и записать её id в Заказ. Для подтверждений, которые
     * идут не через карточку в админке: автоподтверждение при оформлении
     * (Q-032) и оплата картой (AC-02).
     */
    public function registerOrder(int $orderId): void
    {
        orderSetAmoCrm($orderId, $this->pushOrder($orderId));
    }

    /** Создать сделку по Записи (FR-SV-011); возвращает её идентификатор в AmoCRM. */
    public function pushBooking(int $bookingId): string
    {
        $dealId = self::BOOKING_DEAL_ID_PREFIX . $bookingId;

        logInfo('AmoCRM (демо): сделка по Записи создана', ['booking_id' => $bookingId, 'amocrm_id' => $dealId]);

        return $dealId;
    }

    /** Создать сделку и записать её id в Запись — для подтверждённых Записей. */
    public function registerBooking(int $bookingId): void
    {
        bookingSetAmoCrm($bookingId, $this->pushBooking($bookingId));
    }

    /** Обновить сделку после смены статуса или правки Заказа. */
    public function updateDeal(string $dealId, string $status, ?string $total = null): bool
    {
        logInfo('AmoCRM (демо): сделка обновлена', ['amocrm_id' => $dealId, 'status' => $status, 'total' => $total]);

        return true;
    }
}
