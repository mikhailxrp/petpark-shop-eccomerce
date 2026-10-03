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

// Правка состава и цены доступна «до отгрузки» (FR-ORD-004, phase-3.md).
const ORDER_EDITABLE_STATUSES = ['new', 'confirmed', 'assembled'];

// Покупатель сам отменяет Заказ только до отгрузки (ADR-032).
const ORDER_CUSTOMER_CANCELLABLE_STATUSES = ['new', 'confirmed', 'assembled'];

const ORDER_ITEM_MAX_QUANTITY = 999;

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
 * Итоги Заказа по его Позициям (BR-006): цена Позиции в order_items уже
 * эффективная (со скидкой), поэтому здесь скидка не учитывается.
 *
 * @param array<int, array{price: string, quantity: int}> $items
 * @return array{subtotal: string, delivery_cost: string, total: string}
 */
function orderRecalculateTotals(
    array $items,
    string $deliveryMethod,
    string $freeThreshold,
    string $courierCost
): array {
    $lines = array_map(
        static fn (array $item): array => ['price' => $item['price'], 'discount_price' => null, 'quantity' => $item['quantity']],
        $items
    );

    $subtotal = orderCartSubtotal($lines);
    $deliveryCost = orderDeliveryCost($deliveryMethod, $subtotal, $freeThreshold, $courierCost);

    return [
        'subtotal'      => $subtotal,
        'delivery_cost' => $deliveryCost,
        'total'         => orderKopecksToMoney(orderMoneyToKopecks($subtotal) + orderMoneyToKopecks($deliveryCost)),
    ];
}

/** Можно ли править состав и цены Позиций Заказа в этом статусе. */
function orderIsEditable(string $status): bool
{
    return in_array($status, ORDER_EDITABLE_STATUSES, true);
}

/**
 * Показывать ли Покупателю кнопку «Отменить». Только подсказка для View:
 * окончательную проверку статуса делает orderTransition() под блокировкой.
 */
function orderCanBeCancelledByCustomer(string $status): bool
{
    return in_array($status, ORDER_CUSTOMER_CANCELLABLE_STATUSES, true);
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
 * Что переход делает с остатком Вариантов по Позициям Заказа (tz.md §6.3,
 * BR-003, FR-STOCK-004):
 *  - stock_deduct    — Резерв → Списание (new → confirmed);
 *  - reserve_release — резерв снимается, остаток не тронут (new → cancelled);
 *  - stock_restore   — Списание отменяется, количество возвращается в остаток
 *                      (отмена из confirmed и дальше);
 *  - none            — штатное исполнение, остаток не меняется.
 * Неразрешённая пара — InvalidArgumentException, а не 'none': «ничего не
 * делать» для запрещённого перехода скрыло бы ошибку вызывающего кода.
 */
function orderStockAction(string $from, string $to): string
{
    if (!orderCanTransition($from, $to)) {
        throw new InvalidArgumentException("Переход {$from} → {$to} не разрешён");
    }

    return match (true) {
        $from === 'new' && $to === 'confirmed' => 'stock_deduct',
        $from === 'new' && $to === 'cancelled' => 'reserve_release',
        $to === 'cancelled'                    => 'stock_restore',
        default                                => 'none',
    };
}

/**
 * Видит ли текущая сессия этот Заказ: либо она его оформила (номер лежит
 * в $_SESSION['checkout_order_ids']), либо это авторизованный Покупатель-
 * владелец. Один критерий и для страницы успеха, и для страниц оплаты.
 *
 * @param array<string, mixed> $order строка orders (нужны id, user_id)
 */
function orderCanBeViewedBySession(array $order): bool
{
    ensureSessionStarted();

    $ownedInSession = in_array((int) $order['id'], $_SESSION['checkout_order_ids'] ?? [], true);
    $isOwner = isAuthenticated()
        && ($_SESSION['user_role'] ?? null) === 'customer'
        && $order['user_id'] !== null
        && (int) $order['user_id'] === (int) $_SESSION['user_id'];

    return $ownedInSession || $isOwner;
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

/**
 * Исход черновика Заказа из Обращения для ai_draft_outcomes (FR-CHANNELS-003):
 * путь «вручную» при готовом черновике — rejected; путь «по черновику» —
 * accepted, если состав (Вариант → количество) не изменён, иначе edited.
 * Черновика нет (или в нём нет Позиций) — null, исход не пишется.
 *
 * @param array<int, array{variant_id: int, quantity: int}> $draftItems
 * @param array<int, array{variant_id: int, quantity: int}> $lines
 */
function orderDraftOutcome(array $draftItems, array $lines, string $source): ?string
{
    if ($draftItems === []) {
        return null;
    }

    if ($source !== 'draft') {
        return 'rejected';
    }

    $toMap = static function (array $rows): array {
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['variant_id']] = ($map[(int) $row['variant_id']] ?? 0) + (int) $row['quantity'];
        }
        ksort($map);

        return $map;
    };

    return $toMap($draftItems) === $toMap($lines) ? 'accepted' : 'edited';
}
