<?php

declare(strict_types=1);

/**
 * Чистые правила Заказа: деньги, доставка, переходы статусов, адрес.
 * Без БД и без HTTP — покрыто tests/Unit/OrderTest.php.
 *
 * Деньги приходят и уходят строками DECIMAL ("1500.00", как их отдаёт
 * PDO), внутри считаются в целых копейках — никакого float (ADR-022).
 */

/**
 * Допустимые переходы orders.status — диаграмма «Заказ», tz.md §6.3.
 * delivered/picked_up/cancelled — конечные: возврат после выдачи идёт
 * отдельным модулем (order_returns), а не сменой статуса Заказа.
 */
const ORDER_STATUS_TRANSITIONS = [
    'new'              => ['confirmed', 'cancelled'],
    'confirmed'        => ['assembled', 'cancelled'],
    'assembled'        => ['shipped', 'ready_for_pickup', 'cancelled'],
    'shipped'          => ['delivered', 'cancelled'],
    'ready_for_pickup' => ['picked_up', 'cancelled'],
    'delivered'        => [],
    'picked_up'        => [],
    'cancelled'        => [],
];

const ORDER_ADDRESS_MAX_LENGTH = 255;

const ORDER_KOPECKS_PER_RUBLE = 100;

// 32 байта → 64 hex-символа = ровно CHAR(64) колонки orders.checkout_token
// (FR-CHK-007, phase-2.md Таск 5) — тот же паттерн, что у CART_GUEST_TOKEN_*
// в Core/Cart.php. Токен генерируется заново при каждом GET /checkout и не
// привязан к сессии — идемпотентность проверяется через UNIQUE в БД.
const ORDER_CHECKOUT_TOKEN_BYTES = 32;

const ORDER_CHECKOUT_TOKEN_PATTERN = '/^[0-9a-f]{64}$/';

/**
 * "1500.00" / "1500.5" / "1500" → 150000 / 150050 / 150000.
 * Отрицательные суммы и больше двух знаков после точки — ошибка вызывающего кода.
 */
function orderMoneyToKopecks(string $amount): int
{
    if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches) !== 1) {
        throw new InvalidArgumentException("Некорректная денежная сумма: {$amount}");
    }

    $fraction = str_pad($matches[2] ?? '', 2, '0');

    return (int) $matches[1] * ORDER_KOPECKS_PER_RUBLE + (int) $fraction;
}

/**
 * 150050 → "1500.50" — формат DECIMAL(10,2) для записи в БД.
 */
function orderKopecksToMoney(int $kopecks): string
{
    if ($kopecks < 0) {
        throw new InvalidArgumentException("Отрицательная сумма: {$kopecks}");
    }

    return sprintf(
        '%d.%02d',
        intdiv($kopecks, ORDER_KOPECKS_PER_RUBLE),
        $kopecks % ORDER_KOPECKS_PER_RUBLE
    );
}

/**
 * Сумма Позиции: эффективная цена × количество. Скидка — одна вручную
 * заданная цена (BR-002): если задана — действует она, иначе обычная.
 */
function orderLineTotal(string $price, ?string $discountPrice, int $quantity): string
{
    if ($quantity < 1) {
        throw new InvalidArgumentException("Некорректное количество: {$quantity}");
    }

    return orderKopecksToMoney(orderMoneyToKopecks($discountPrice ?? $price) * $quantity);
}

/**
 * Итог корзины без доставки (FR-CART-004) — сумма всех Позиций.
 *
 * @param array<int, array{price: string, discount_price: ?string, quantity: int}> $lines
 */
function orderCartSubtotal(array $lines): string
{
    $total = 0;

    foreach ($lines as $line) {
        $total += orderMoneyToKopecks(
            orderLineTotal($line['price'], $line['discount_price'], $line['quantity'])
        );
    }

    return orderKopecksToMoney($total);
}

/**
 * Стоимость доставки (BR-006, FR-SHIP-002): самовывоз — 0 всегда;
 * курьер — 0 при сумме Заказа ≥ порога, иначе фиксированная цена.
 * Порог и цену передаёт вызывающий код из config.php.
 */
function orderDeliveryCost(
    string $deliveryMethod,
    string $subtotal,
    string $freeThreshold,
    string $courierCost
): string {
    return match ($deliveryMethod) {
        'pickup'  => orderKopecksToMoney(0),
        'courier' => orderMoneyToKopecks($subtotal) >= orderMoneyToKopecks($freeThreshold)
            ? orderKopecksToMoney(0)
            : orderKopecksToMoney(orderMoneyToKopecks($courierCost)),
        default   => throw new InvalidArgumentException("Неизвестный способ доставки: {$deliveryMethod}"),
    };
}

/**
 * Разрешён ли переход статуса Заказа. Неизвестный статус — не разрешён.
 * Запись в БД — отдельная функция-переход в Model, она обязана вызывать эту.
 */
function orderCanTransition(string $from, string $to): bool
{
    return in_array($to, ORDER_STATUS_TRANSITIONS[$from] ?? [], true);
}

/**
 * Адрес курьерской доставки (FR-SHIP-003) в одну строку для
 * orders.delivery_address: город фиксирован, пустые части пропускаются,
 * результат обрезается до длины колонки.
 */
function orderBuildDeliveryAddress(
    string $city,
    string $street,
    string $house,
    string $apartment,
    string $comment
): string {
    $parts = array_filter([
        trim($city),
        trim($street),
        trim($house) !== '' ? 'д. ' . trim($house) : '',
        trim($apartment) !== '' ? 'кв. ' . trim($apartment) : '',
    ], static fn (string $part): bool => $part !== '');

    $address = implode(', ', $parts);

    if (trim($comment) !== '') {
        $address .= '. Комментарий курьеру: ' . trim($comment);
    }

    return mb_substr($address, 0, ORDER_ADDRESS_MAX_LENGTH);
}
