<?php

declare(strict_types=1);

/**
 * Модель Корзины — только SQL через PDO, возвращает массивы (php.md).
 * FR-CART-001–004, BR-004; database.md `cart_items` (ADR-015, ADR-016).
 *
 * Каждый запрос к строкам корзины фильтруется по владельцу (cartOwner(),
 * Core/Cart.php) — чужую строку не прочитать и не изменить, даже
 * подставив её id в форму.
 */

/**
 * WHERE-условие владельца: ровно одно из user_id / session_id
 * (database.md — проверяется в Model, не constraint'ом БД).
 *
 * @param array{user_id: ?int, session_id: ?string} $owner
 * @return array{sql: string, param: int|string}
 */
function cartOwnerCondition(array $owner): array
{
    $userId = $owner['user_id'] ?? null;
    $sessionId = $owner['session_id'] ?? null;

    return match (true) {
        $userId !== null && $sessionId === null => ['sql' => 'ci.user_id = ?', 'param' => $userId],
        $userId === null && $sessionId !== null && $sessionId !== '' => ['sql' => 'ci.session_id = ?', 'param' => $sessionId],
        default => throw new InvalidArgumentException('Владелец корзины: должно быть задано ровно одно из user_id / session_id'),
    };
}

/**
 * Позиции корзины с актуальными ценой и остатком из product_variants.
 * Неактивные Варианты/Товары не показываются и в сумму не входят.
 *
 * @param array{user_id: ?int, session_id: ?string} $owner
 * @return array<int, array<string, mixed>>
 */
function cartItemsForOwner(array $owner): array
{
    ['sql' => $ownerSql, 'param' => $ownerParam] = cartOwnerCondition($owner);

    $stmt = getPdo()->prepare("
        SELECT
            ci.id, ci.variant_id, ci.quantity, ci.price_seen,
            v.sku, v.price, v.discount_price, v.stock_quantity, v.reserved_quantity,
            p.name, p.slug
        FROM cart_items ci
        JOIN product_variants v ON v.id = ci.variant_id AND v.is_active = 1
        JOIN products p ON p.id = v.product_id AND p.is_active = 1
        WHERE {$ownerSql}
        ORDER BY ci.id ASC
    ");
    $stmt->execute([$ownerParam]);

    return $stmt->fetchAll();
}

/**
 * Одна Позиция владельца (с ценой и остатком Варианта) — для изменения
 * количества. null — строки нет, она чужая или Вариант неактивен.
 *
 * @param array{user_id: ?int, session_id: ?string} $owner
 */
function cartFindItem(array $owner, int $itemId): ?array
{
    ['sql' => $ownerSql, 'param' => $ownerParam] = cartOwnerCondition($owner);

    $stmt = getPdo()->prepare("
        SELECT ci.id, ci.variant_id, ci.quantity, v.stock_quantity, v.reserved_quantity
        FROM cart_items ci
        JOIN product_variants v ON v.id = ci.variant_id AND v.is_active = 1
        JOIN products p ON p.id = v.product_id AND p.is_active = 1
        WHERE ci.id = ? AND {$ownerSql}
    ");
    $stmt->execute([$itemId, $ownerParam]);
    $row = $stmt->fetch();

    return $row !== false ? $row : null;
}

/**
 * Позиция владельца с этим Вариантом — повторное «В корзину» увеличивает
 * её количество, а не создаёт дубль.
 *
 * @param array{user_id: ?int, session_id: ?string} $owner
 * @return array{id: int, quantity: int}|null
 */
function cartFindItemByVariant(array $owner, int $variantId): ?array
{
    ['sql' => $ownerSql, 'param' => $ownerParam] = cartOwnerCondition($owner);

    $stmt = getPdo()->prepare("
        SELECT ci.id, ci.quantity
        FROM cart_items ci
        WHERE ci.variant_id = ? AND {$ownerSql}
        ORDER BY ci.id ASC
        LIMIT 1
    ");
    $stmt->execute([$variantId, $ownerParam]);
    $row = $stmt->fetch();

    return $row !== false ? $row : null;
}

/**
 * Вариант, который можно положить в корзину: активный Вариант активного
 * Товара. Цена/остаток — для проверки BR-004 и снэпшота price_seen.
 *
 * @return array{id: int, price: string, discount_price: ?string, stock_quantity: int, reserved_quantity: int}|null
 */
function cartFindVariant(int $variantId): ?array
{
    $stmt = getPdo()->prepare('
        SELECT v.id, v.price, v.discount_price, v.stock_quantity, v.reserved_quantity
        FROM product_variants v
        JOIN products p ON p.id = v.product_id AND p.is_active = 1
        WHERE v.id = ? AND v.is_active = 1
    ');
    $stmt->execute([$variantId]);
    $row = $stmt->fetch();

    return $row !== false ? $row : null;
}

/**
 * @param array{user_id: ?int, session_id: ?string} $owner
 */
function cartInsertItem(array $owner, int $variantId, int $quantity, string $priceSeen): void
{
    cartOwnerCondition($owner);

    $stmt = getPdo()->prepare('
        INSERT INTO cart_items (session_id, user_id, variant_id, quantity, price_seen)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([$owner['session_id'], $owner['user_id'], $variantId, $quantity, $priceSeen]);
}

/**
 * Повторное добавление: новое количество и свежий снэпшот цены — её
 * Покупатель только что видел на карточке.
 *
 * @param array{user_id: ?int, session_id: ?string} $owner
 */
function cartUpdateItemOnAdd(array $owner, int $itemId, int $quantity, string $priceSeen): void
{
    ['sql' => $ownerSql, 'param' => $ownerParam] = cartOwnerCondition($owner);

    $stmt = getPdo()->prepare("
        UPDATE cart_items ci SET ci.quantity = ?, ci.price_seen = ?
        WHERE ci.id = ? AND {$ownerSql}
    ");
    $stmt->execute([$quantity, $priceSeen, $itemId, $ownerParam]);
}

/**
 * @param array{user_id: ?int, session_id: ?string} $owner
 */
function cartUpdateItemQuantity(array $owner, int $itemId, int $quantity): void
{
    ['sql' => $ownerSql, 'param' => $ownerParam] = cartOwnerCondition($owner);

    $stmt = getPdo()->prepare("
        UPDATE cart_items ci SET ci.quantity = ?
        WHERE ci.id = ? AND {$ownerSql}
    ");
    $stmt->execute([$quantity, $itemId, $ownerParam]);
}

/**
 * @param array{user_id: ?int, session_id: ?string} $owner
 * @return bool true — строка удалена, false — её не было или она чужая
 */
function cartDeleteItem(array $owner, int $itemId): bool
{
    ['sql' => $ownerSql, 'param' => $ownerParam] = cartOwnerCondition($owner);

    $stmt = getPdo()->prepare("DELETE ci FROM cart_items ci WHERE ci.id = ? AND {$ownerSql}");
    $stmt->execute([$itemId, $ownerParam]);

    return $stmt->rowCount() > 0;
}

/**
 * Переносит строку корзины Гостя на вошедшего Покупателя (слияние при
 * входе, cartMergeGuestIntoUser()) — у Покупателя ещё нет этого
 * Варианта, поэтому строка просто меняет владельца, price_seen не
 * трогается.
 */
function cartReassignItemToUser(array $guestOwner, int $itemId, int $userId, int $quantity): void
{
    ['sql' => $ownerSql, 'param' => $ownerParam] = cartOwnerCondition($guestOwner);

    $stmt = getPdo()->prepare("
        UPDATE cart_items ci SET ci.user_id = ?, ci.session_id = NULL, ci.quantity = ?
        WHERE ci.id = ? AND {$ownerSql}
    ");
    $stmt->execute([$userId, $quantity, $itemId, $ownerParam]);
}

/**
 * Очистка корзины владельца после успешного оформления Заказа
 * (phase-2.md, Таск 5) — вызывается внутри транзакции orderCreate(),
 * тем же PDO-подключением.
 *
 * @param array{user_id: ?int, session_id: ?string} $owner
 */
function cartClearForOwner(array $owner): void
{
    ['sql' => $ownerSql, 'param' => $ownerParam] = cartOwnerCondition($owner);

    $stmt = getPdo()->prepare("DELETE ci FROM cart_items ci WHERE {$ownerSql}");
    $stmt->execute([$ownerParam]);
}

/**
 * Слияние корзины Гостя с корзиной вошедшего Покупателя (FR-CART-005,
 * Q-049): совпадающие Варианты — количества складываются и ограничены
 * доступным остатком, остальные строки Гостя просто переезжают на
 * user_id. Одна транзакция — не бывает наполовину слитой корзины.
 */
function cartMergeGuestIntoUser(string $guestToken, int $userId): void
{
    $guestOwner = ['user_id' => null, 'session_id' => $guestToken];
    $userOwner = ['user_id' => $userId, 'session_id' => null];

    $pdo = getPdo();
    $pdo->beginTransaction();

    try {
        foreach (cartItemsForOwner($guestOwner) as $guestRow) {
            $guestItemId = (int) $guestRow['id'];
            $available = cartAvailableQuantity((int) $guestRow['stock_quantity'], (int) $guestRow['reserved_quantity']);
            $existing = cartFindItemByVariant($userOwner, (int) $guestRow['variant_id']);

            if ($existing !== null) {
                $merged = cartClampQuantity((int) $existing['quantity'] + (int) $guestRow['quantity'], $available);
                if ($merged > 0) {
                    cartUpdateItemQuantity($userOwner, (int) $existing['id'], $merged);
                } else {
                    cartDeleteItem($userOwner, (int) $existing['id']);
                }
                cartDeleteItem($guestOwner, $guestItemId);
                continue;
            }

            $quantity = cartClampQuantity((int) $guestRow['quantity'], $available);
            if ($quantity > 0) {
                cartReassignItemToUser($guestOwner, $guestItemId, $userId, $quantity);
            } else {
                cartDeleteItem($guestOwner, $guestItemId);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
