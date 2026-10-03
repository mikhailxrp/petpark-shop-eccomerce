<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Заглушка выгрузки на Wildberries/Ozon (ADR-001, демо-проект): реального API
 * нет, «выгрузка» — это пересчёт `marketplace_listings`. Контракт
 * `syncCatalog()` сохранён для будущей реальной интеграции — вызывающий код
 * при её подключении не меняется.
 */
final class MarketplaceSync
{
    private const ORDER_MIN_LINES = 1;
    private const ORDER_MAX_LINES = 3;
    private const LINE_MIN_QUANTITY = 1;
    private const LINE_MAX_QUANTITY = 2;

    /**
     * Синхронизировать каталог с одной площадкой.
     *
     * @return array{listed: int, delisted: int}
     * @throws \InvalidArgumentException неизвестная площадка
     */
    public function syncCatalog(string $marketplace): array
    {
        $result = marketplaceListingSync($marketplace);

        logInfo('Маркетплейс (демо): каталог синхронизирован', [
            'marketplace' => $marketplace,
            'listed' => $result['listed'],
            'delisted' => $result['delisted'],
        ]);

        return $result;
    }

    /**
     * Демо-заглушка «новых заказов с площадки»: реального API нет, поэтому
     * Заказ генерируется из 1–3 выгруженных на площадку Вариантов. Контракт
     * (массив `external_order_id` + `lines`) совпадёт с реальным ответом API.
     *
     * @return array{external_order_id: string, lines: array<int, array{variant_id: int, quantity: int}>}|null
     *         null — на площадке нет выгруженных Вариантов
     * @throws \InvalidArgumentException неизвестная площадка
     */
    public function fetchNewOrders(string $marketplace): ?array
    {
        $listed = marketplaceListingRandomListed($marketplace, random_int(self::ORDER_MIN_LINES, self::ORDER_MAX_LINES));
        if ($listed === []) {
            return null;
        }

        $lines = array_map(static fn(array $row): array => [
            'variant_id' => (int) $row['variant_id'],
            'quantity'   => random_int(self::LINE_MIN_QUANTITY, self::LINE_MAX_QUANTITY),
        ], $listed);

        return [
            'external_order_id' => strtoupper(substr($marketplace, 0, 2)) . '-' . date('ymd') . '-' . bin2hex(random_bytes(3)),
            'lines'             => $lines,
        ];
    }

    /**
     * Принять демо-Заказ с площадки: fetchNewOrders() → создание Заказа.
     *
     * @return array{status: 'created', order_id: int}
     *       | array{status: 'exists', order_id: int}
     *       | array{status: 'unavailable', product_name: string}
     *       | array{status: 'not_listed'}
     *       | array{status: 'no_listed'}
     * @throws \InvalidArgumentException неизвестная площадка
     */
    public function receiveOrder(string $marketplace): array
    {
        $incoming = $this->fetchNewOrders($marketplace);
        if ($incoming === null) {
            return ['status' => 'no_listed'];
        }

        $result = orderCreateFromMarketplace($marketplace, $incoming['external_order_id'], $incoming['lines']);

        logInfo('Маркетплейс (демо): приём заказа', [
            'marketplace' => $marketplace,
            'external_order_id' => $incoming['external_order_id'],
            'status' => $result['status'],
            'order_id' => $result['order_id'] ?? null,
        ]);

        return $result;
    }
}
