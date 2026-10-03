<?php

declare(strict_types=1);

/**
 * Правила выгрузки на маркетплейсы — phase-9.md, Таск 1 (BR-007, FR-MARKET-001).
 * Чистая логика без БД. Деньги — строки DECIMAL, внутри целые копейки (ADR-022).
 */

const MARKETPLACES = ['wildberries', 'ozon'];

const MARKETPLACE_PERCENT_BASE = 100;

/**
 * Площадка известна системе, иначе исключение — до любого обращения к БД.
 */
function marketplaceAssertKnown(string $marketplace): void
{
    if (!in_array($marketplace, MARKETPLACES, true)) {
        throw new InvalidArgumentException("Неизвестная площадка: {$marketplace}");
    }
}

/**
 * Цена на площадке: эффективная цена Варианта (Скидка, если задана) плюс
 * наценка, округление вверх до целого рубля. "1000.00" → "1150.00",
 * "899.00" → "1034.00" (1033.85 вверх), "1000.01" → "1151.00".
 */
function marketplacePrice(
    string $price,
    ?string $discountPrice = null,
    int $markupPercent = MARKETPLACE_MARKUP_PERCENT
): string {
    if ($markupPercent < 0) {
        throw new InvalidArgumentException("Некорректная наценка: {$markupPercent}");
    }

    $kopecks = orderMoneyToKopecks($discountPrice ?? $price);
    $withMarkup = $kopecks * (MARKETPLACE_PERCENT_BASE + $markupPercent);
    $divisor = MARKETPLACE_PERCENT_BASE * ORDER_KOPECKS_PER_RUBLE;
    $rubles = intdiv($withMarkup + $divisor - 1, $divisor);

    return orderKopecksToMoney($rubles * ORDER_KOPECKS_PER_RUBLE);
}

/**
 * Вариант выгружается, если активны он и его Товар и есть доступный
 * остаток (остаток минус резерв больше нуля).
 */
function marketplaceIsListable(
    int $stockQuantity,
    int $reservedQuantity,
    bool $variantIsActive,
    bool $productIsActive
): bool {
    return $variantIsActive && $productIsActive && $stockQuantity - $reservedQuantity > 0;
}
