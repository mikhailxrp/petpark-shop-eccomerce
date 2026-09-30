<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Заглушка МойСклад (ADR-001, демо-проект): реального API нет, «внешний»
 * остаток вводит сотрудник на странице /admin/stock. Контракт
 * `syncVariant()` сохранён для будущей реальной интеграции — вызывающий код
 * при её подключении не меняется.
 */
final class MoySklad
{
    /** Записать остаток Варианта, пришедший из МойСклад. */
    public function syncVariant(int $variantId, int $externalQuantity): void
    {
        productVariantSetStockFromMoySklad($variantId, $externalQuantity);

        logInfo('МойСклад (демо): остаток синхронизирован', [
            'variant_id' => $variantId,
            'stock_quantity' => $externalQuantity,
        ]);
    }
}
