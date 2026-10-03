<?php

declare(strict_types=1);

/**
 * Чистая логика формы Вариантов Товара (phase-7.md, Таск 9; FR-ADM-002,
 * FR-DISC-001, BR-002): артикул, цена, начальный остаток, скидка, значения
 * «вес упаковки»/«вкус». Без БД и HTTP — покрыто unit-тестами; SQL — в
 * src/Models/Product.php. Деньги — строкой «1500.00» и в копейках, не float.
 */

const PRODUCT_VARIANT_SKU_MAX = 64; // product_variants.sku
const PRODUCT_VARIANT_PRICE_MAX_KOPECKS = 9999999999; // DECIMAL(10,2)
const PRODUCT_VARIANT_STOCK_MAX = 1000000; // как на /admin/stock (StockController)
const PRODUCT_VARIANT_ATTRIBUTE_MAX = 150; // product_variant_attributes.attr_value

// Свойства Варианта (tz.md §6.2); остальные имена из POST не читаются.
const PRODUCT_VARIANT_ATTRIBUTE_NAMES = ['вес_упаковки', 'вкус'];

/**
 * «1 500,5» / «1500.50» → «1500.50». null — не сумма, ноль или больше DECIMAL(10,2).
 */
function productVariantMoney(string $raw): ?string
{
    $clean = str_replace([' ', ','], ['', '.'], trim($raw));

    if (preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $clean) !== 1) {
        return null;
    }

    $kopecks = orderMoneyToKopecks($clean);

    return $kopecks > 0 && $kopecks <= PRODUCT_VARIANT_PRICE_MAX_KOPECKS
        ? orderKopecksToMoney($kopecks)
        : null;
}

/**
 * Проверка полей Варианта.
 *
 * Новый Вариант (`$currentPrice === null`): `sku`, `price`, `stock_quantity`,
 * `discount_price`, атрибуты. Существующий (`$currentPrice` — цена из БД):
 * только `discount_price`, `is_active` и атрибуты — `sku`, цена и остаток из
 * POST не читаются, даже если пришли (цена вводится один раз, остаток правит
 * /admin/stock). Скидка сравнивается с ценой в копейках (BR-002: ниже цены).
 *
 * @param array<string, mixed> $input сырой POST
 * @param string|null $currentPrice цена существующего Варианта «1500.00»; null — новый
 * @return array{0: array<string, mixed>, 1: array<string, string>} [значения, ошибки по полям]
 */
function productVariantValidate(array $input, ?string $currentPrice): array
{
    $isNew = $currentPrice === null;
    $text = static fn (string $key): string => is_string($input[$key] ?? null)
        ? trim(mb_scrub($input[$key]))
        : '';

    $values = [];
    $errors = [];
    $price = $currentPrice;

    if ($isNew) {
        $values['sku'] = $text('sku');
        if ($values['sku'] === ''
            || strlen($values['sku']) > PRODUCT_VARIANT_SKU_MAX
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/', $values['sku']) !== 1
        ) {
            $errors['sku'] = 'Артикул — латиница, цифры, точка, дефис и слэш, до ' . PRODUCT_VARIANT_SKU_MAX . ' символов.';
        }

        $price = productVariantMoney($text('price'));
        $values['price'] = $price;
        if ($price === null) {
            $errors['price'] = 'Укажите цену — число больше нуля, не более двух знаков после запятой.';
        }

        $stockRaw = $text('stock_quantity');
        $values['stock_quantity'] = 0;
        if ($stockRaw !== '' || is_array($input['stock_quantity'] ?? null)) {
            if (preg_match('/^\d{1,7}$/', $stockRaw) === 1 && (int) $stockRaw <= PRODUCT_VARIANT_STOCK_MAX) {
                $values['stock_quantity'] = (int) $stockRaw;
            } else {
                $errors['stock_quantity'] = 'Остаток — целое число от 0 до ' . PRODUCT_VARIANT_STOCK_MAX . '.';
            }
        }
    } else {
        $values['is_active'] = ($input['is_active'] ?? '') === '1';
    }

    $values['discount_price'] = null;
    $discountRaw = $text('discount_price');
    if ($discountRaw !== '') {
        $discount = productVariantMoney($discountRaw);
        if ($discount === null) {
            $errors['discount_price'] = 'Скидочная цена — число больше нуля, не более двух знаков после запятой.';
        } elseif ($price !== null && orderMoneyToKopecks($discount) >= orderMoneyToKopecks($price)) {
            $errors['discount_price'] = 'Скидочная цена должна быть ниже обычной.';
        } else {
            $values['discount_price'] = $discount;
        }
    }

    $values['attributes'] = [];
    $attributesInput = is_array($input['attributes'] ?? null) ? $input['attributes'] : [];
    foreach (PRODUCT_VARIANT_ATTRIBUTE_NAMES as $name) {
        $value = is_string($attributesInput[$name] ?? null) ? trim(mb_scrub($attributesInput[$name])) : '';
        if ($value === '') {
            continue;
        }
        if (mb_strlen($value) > PRODUCT_VARIANT_ATTRIBUTE_MAX) {
            $errors['attributes'] = 'Значение — не длиннее ' . PRODUCT_VARIANT_ATTRIBUTE_MAX . ' символов.';
            continue;
        }
        $values['attributes'][$name] = $value;
    }

    return [$values, $errors];
}
