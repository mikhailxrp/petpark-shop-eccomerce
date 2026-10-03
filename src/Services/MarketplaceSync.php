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
}
