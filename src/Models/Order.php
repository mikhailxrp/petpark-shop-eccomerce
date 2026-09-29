<?php

declare(strict_types=1);

/**
 * Модель Заказа — только SQL через PDO, возвращает массивы (php.md).
 * Денежные правила и адрес — в Core/Order.php. phase-2.md, Таск 5:
 * FR-CHK-004/007, FR-AUTH-002, BR-003.
 *
 * orderCreate() — единственное место, где создаётся Заказ: одна
 * транзакция на идемпотентность + автоаккаунт + резерв остатка +
 * order_items + очистку корзины (general.md: многошаговая атомарная
 * запись — один Model-функция, одна транзакция).
 */

/**
 * @param array{name: string, phone: string, email: string} $contact
 * @param array{user_id: ?int, session_id: ?string} $owner
 * @return array{status: 'created', order_id: int, new_account: array{name: string, email: string, password: string}|null}
 *       | array{status: 'exists', order_id: int}
 *       | array{status: 'unavailable', product_name: string}
 *       | array{status: 'empty'}
 */
function orderCreate(
    array $owner,
    ?int $sessionUserId,
    array $contact,
    string $deliveryMethod,
    ?string $deliveryAddress,
    string $paymentMethod,
    ?string $customerNote,
    string $checkoutToken
): array {
    $existing = orderFindByCheckoutToken($checkoutToken);
    if ($existing !== null) {
        return ['status' => 'exists', 'order_id' => (int) $existing['id']];
    }

    $cartRows = cartItemsForOwner($owner);
    if ($cartRows === []) {
        return ['status' => 'empty'];
    }

    $lines = [];
    foreach ($cartRows as $row) {
        $lines[] = [
            'price'          => (string) $row['price'],
            'discount_price' => $row['discount_price'] !== null ? (string) $row['discount_price'] : null,
            'quantity'       => (int) $row['quantity'],
        ];
    }

    $subtotal = orderCartSubtotal($lines);
    $deliveryCost = orderDeliveryCost($deliveryMethod, $subtotal, DELIVERY_FREE_THRESHOLD, DELIVERY_COURIER_COST);
    $total = orderKopecksToMoney(orderMoneyToKopecks($subtotal) + orderMoneyToKopecks($deliveryCost));

    $pdo = getPdo();
    $pdo->beginTransaction();

    try {
        $newAccount = null;
        $userId = $sessionUserId;

        if ($userId === null) {
            $existingUser = userFindByEmail($contact['email']);
            if ($existingUser !== null) {
                $userId = (int) $existingUser['id'];
            } else {
                $password = generatePassword();
                $userId = userCreateCustomer(
                    $contact['name'],
                    $contact['email'],
                    $contact['phone'],
                    password_hash($password, PASSWORD_DEFAULT)
                );
                $newAccount = ['name' => $contact['name'], 'email' => $contact['email'], 'password' => $password];
            }
        }

        $stmt = $pdo->prepare('
            INSERT INTO orders (
                user_id, status, payment_status, delivery_method, payment_method,
                delivery_cost, delivery_address, contact_name, contact_phone, contact_email,
                customer_note, checkout_token, reserved_until, total
            ) VALUES (
                :user_id, \'new\', \'unpaid\', :delivery_method, :payment_method,
                :delivery_cost, :delivery_address, :contact_name, :contact_phone, :contact_email,
                :customer_note, :checkout_token, DATE_ADD(NOW(), INTERVAL :reserve_minutes MINUTE), :total
            )
        ');
        $stmt->execute([
            'user_id'          => $userId,
            'delivery_method'  => $deliveryMethod,
            'payment_method'   => $paymentMethod,
            'delivery_cost'    => $deliveryCost,
            'delivery_address' => $deliveryAddress,
            'contact_name'     => $contact['name'],
            'contact_phone'    => $contact['phone'],
            'contact_email'    => $contact['email'],
            'customer_note'    => $customerNote,
            'checkout_token'   => $checkoutToken,
            'reserve_minutes'  => ORDER_RESERVE_MINUTES,
            'total'            => $total,
        ]);
        $orderId = (int) $pdo->lastInsertId();

        $variantAttributes = productVariantAttributesForVariants(array_map(
            static fn (array $row): int => (int) $row['variant_id'],
            $cartRows
        ));

        $unavailableProductName = null;

        // :qty встречается дважды — с PDO::ATTR_EMULATE_PREPARES=false (Database.php)
        // нативный драйвер MySQL не поддерживает повтор одного named-плейсхолдера
        // в запросе, поэтому у второго вхождения отдельное имя с тем же значением.
        $reserveStmt = $pdo->prepare('
            UPDATE product_variants
            SET reserved_quantity = reserved_quantity + :qty
            WHERE id = :id AND stock_quantity - reserved_quantity >= :qty_check
        ');
        $insertItemStmt = $pdo->prepare('
            INSERT INTO order_items (order_id, variant_id, product_name, variant_label, price, quantity)
            VALUES (:order_id, :variant_id, :product_name, :variant_label, :price, :quantity)
        ');

        foreach ($cartRows as $row) {
            $variantId = (int) $row['variant_id'];
            $quantity = (int) $row['quantity'];

            $reserveStmt->execute(['qty' => $quantity, 'id' => $variantId, 'qty_check' => $quantity]);
            if ($reserveStmt->rowCount() === 0) {
                $unavailableProductName = (string) $row['name'];
                break;
            }

            $attributes = $variantAttributes[$variantId] ?? [];
            $variantLabel = $attributes !== [] ? implode(', ', $attributes) : (string) $row['sku'];

            $insertItemStmt->execute([
                'order_id'      => $orderId,
                'variant_id'    => $variantId,
                'product_name'  => (string) $row['name'],
                'variant_label' => $variantLabel,
                'price'         => orderLineTotal((string) $row['price'], $row['discount_price'] !== null ? (string) $row['discount_price'] : null, 1),
                'quantity'      => $quantity,
            ]);
        }

        if ($unavailableProductName !== null) {
            $pdo->rollBack();
            return ['status' => 'unavailable', 'product_name' => $unavailableProductName];
        }

        cartClearForOwner($owner);

        $pdo->commit();

        return ['status' => 'created', 'order_id' => $orderId, 'new_account' => $newAccount];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Гонка двух одинаковых checkout_token (двойной сабмит почти
        // одновременно) — UNIQUE(checkout_token) словил дубль раньше, чем
        // наша проверка в начале функции; это не ошибка, а тот же Заказ.
        if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'checkout_token')) {
            $raceWinner = orderFindByCheckoutToken($checkoutToken);
            if ($raceWinner !== null) {
                return ['status' => 'exists', 'order_id' => (int) $raceWinner['id']];
            }
        }

        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

/**
 * @return array{id: int}|null
 */
function orderFindByCheckoutToken(string $token): ?array
{
    $stmt = getPdo()->prepare('SELECT id FROM orders WHERE checkout_token = ? LIMIT 1');
    $stmt->execute([$token]);

    $row = $stmt->fetch();
    return $row !== false ? $row : null;
}

/**
 * Заказ для страницы «Заказ оформлен» (FR-CHK-005) и проверки владения.
 *
 * @return array<string, mixed>|null
 */
function orderFindById(int $id): ?array
{
    $stmt = getPdo()->prepare('
        SELECT
            id, user_id, status, payment_status, delivery_method, payment_method,
            delivery_cost, delivery_address, contact_name, contact_phone, contact_email,
            customer_note, total, created_at
        FROM orders
        WHERE id = ?
        LIMIT 1
    ');
    $stmt->execute([$id]);

    $row = $stmt->fetch();
    return $row !== false ? $row : null;
}

/**
 * @return array<int, array<string, mixed>>
 */
function orderItemsForOrder(int $orderId): array
{
    $stmt = getPdo()->prepare('
        SELECT product_name, variant_label, price, quantity
        FROM order_items
        WHERE order_id = ?
        ORDER BY id ASC
    ');
    $stmt->execute([$orderId]);

    return $stmt->fetchAll();
}
