<?php

declare(strict_types=1);

/**
 * Чистые правила Корзины (FR-CART-001–004, BR-004): владелец корзины,
 * количество в пределах остатка, суммы, вывод денег. SQL — в
 * src/Models/Cart.php. Покрыто tests/Unit/CartTest.php.
 *
 * Суммы считаются только из цен product_variants через Core/Order.php
 * (копейки, без float) — cart_items.price_seen в расчёт не входит
 * (ADR-016).
 */

// 32 байта → 64 hex-символа = ровно VARCHAR(64) колонки cart_items.session_id
const CART_GUEST_TOKEN_BYTES = 32;

const CART_GUEST_TOKEN_PATTERN = '/^[0-9a-f]{64}$/';

// Долгоживущая cookie корзины Гостя — 30 дней, переживает закрытие
// браузера (FR-CART-005, ADR-015); PHP-сессия для этого не подходит.
const CART_GUEST_COOKIE_NAME = 'cart_guest_token';

const CART_GUEST_COOKIE_DAYS = 30;

/**
 * Чья корзина у текущего запроса: авторизованный Покупатель — по
 * user_id, все остальные (Гость, персонал) — по случайному токену в
 * долгоживущей cookie (ADR-015), не равному session_id() PHP.
 *
 * @return array{user_id: ?int, session_id: ?string} Ровно одно поле не null
 */
function cartOwner(): array
{
    ensureSessionStarted();

    $userId = normalizeUserId($_SESSION['user_id'] ?? null);
    if ($userId !== null && ($_SESSION['user_role'] ?? null) === 'customer') {
        return ['user_id' => $userId, 'session_id' => null];
    }

    $token = getOrSetPersistentToken(
        CART_GUEST_COOKIE_NAME,
        CART_GUEST_TOKEN_PATTERN,
        CART_GUEST_TOKEN_BYTES,
        CART_GUEST_COOKIE_DAYS
    );

    return ['user_id' => null, 'session_id' => $token];
}

/**
 * Количество из формы: целое ≥ 0 (FR-CART-001, правило 1). Всё прочее —
 * отрицательное, дробное, текст, массив — null, вызывающий код отклоняет.
 */
function cartNormalizeQuantity(mixed $raw): ?int
{
    if (is_int($raw)) {
        return $raw >= 0 ? $raw : null;
    }

    if (is_string($raw)) {
        $raw = trim($raw);
        if ($raw !== '' && ctype_digit($raw)) {
            return (int) $raw;
        }
    }

    return null;
}

/**
 * Положительный id из формы (variant_id, item_id) — иначе null.
 */
function cartNormalizeId(mixed $raw): ?int
{
    $id = cartNormalizeQuantity($raw);

    return $id !== null && $id > 0 ? $id : null;
}

/**
 * Доступный остаток Варианта (BR-004) — тот же принцип, что метка
 * наличия каталога (catalogAvailabilityStatus()): stock - reserved.
 */
function cartAvailableQuantity(int $stockQuantity, int $reservedQuantity): int
{
    return max(0, $stockQuantity - $reservedQuantity);
}

/**
 * Запрошенное количество, ограниченное доступным остатком
 * (FR-CART-001, правило 2).
 */
function cartClampQuantity(int $requested, int $available): int
{
    return max(0, min($requested, $available));
}

/**
 * Строки корзины из Model → строки с суммой Позиции и итог корзины.
 * Доставка в корзине не считается (FR-CART-003, правило 2).
 *
 * @param array<int, array<string, mixed>> $rows cartItemsForOwner() — price, discount_price (строки DECIMAL), quantity, stock_quantity, reserved_quantity
 * @return array{items: array<int, array<string, mixed>>, subtotal: string}
 */
function cartSummarize(array $rows): array
{
    $items = [];
    $lines = [];

    foreach ($rows as $row) {
        $quantity = (int) $row['quantity'];
        $discountPrice = $row['discount_price'] !== null ? (string) $row['discount_price'] : null;

        $row['line_total'] = orderLineTotal((string) $row['price'], $discountPrice, $quantity);
        $row['available'] = cartAvailableQuantity((int) $row['stock_quantity'], (int) $row['reserved_quantity']);
        $items[] = $row;

        $lines[] = [
            'price'          => (string) $row['price'],
            'discount_price' => $discountPrice,
            'quantity'       => $quantity,
        ];
    }

    return ['items' => $items, 'subtotal' => orderCartSubtotal($lines)];
}

/**
 * "1500.00" → "1500", "1500.50" → "1500,50" — вывод денег в корзине.
 * Копейки показываются, только если они есть; без float (в отличие от
 * seoFormatPrice(), которая округляет до рубля для каталога).
 */
function cartFormatMoney(string $amount): string
{
    $kopecks = orderMoneyToKopecks($amount);
    $rubles = intdiv($kopecks, ORDER_KOPECKS_PER_RUBLE);
    $fraction = $kopecks % ORDER_KOPECKS_PER_RUBLE;

    return $fraction === 0 ? (string) $rubles : sprintf('%d,%02d', $rubles, $fraction);
}
