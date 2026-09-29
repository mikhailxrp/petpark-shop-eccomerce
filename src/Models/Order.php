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

        // Оплата при получении: подтверждаем сразу (Q-032), в той же
        // транзакции — orderTransition() присоединяется к уже открытой.
        if ($paymentMethod === 'cash_or_card_on_delivery' && !orderTransition($orderId, 'confirmed')) {
            throw new RuntimeException("Не удалось подтвердить Заказ {$orderId} при создании");
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

/**
 * Единственное место, где меняется orders.status (php.md, «Cart & orders»).
 * Возвращает false, если Заказа нет или переход из текущего статуса не
 * разрешён (в т.ч. повторный вызов после уже выполненного перехода) —
 * это не ошибка, вызывающий код просто ничего не делает.
 *
 * Работает в транзакции: открывает свою либо присоединяется к уже
 * открытой (orderCreate() подтверждает Заказ «при получении» в той же
 * транзакции, что и создаёт его). Строка Заказа блокируется
 * SELECT ... FOR UPDATE, поэтому два одновременных callback'а не
 * спишут остаток дважды.
 *
 * Пока реализованы только переходы из `new`: остаток списывается при
 * `confirmed`, резерв снимается при `cancelled`. Остальные допустимые
 * по карте переходы появятся в Фазе 3 вместе с возвратом остатка.
 *
 * @param string|null $paymentStatus новый orders.payment_status; null — не менять
 */
function orderTransition(int $orderId, string $toStatus, ?string $paymentStatus = null): bool
{
    $pdo = getPdo();
    $ownsTransaction = !$pdo->inTransaction();

    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare('SELECT status FROM orders WHERE id = ? FOR UPDATE');
        $stmt->execute([$orderId]);
        $fromStatus = $stmt->fetchColumn();

        if ($fromStatus === false || !orderCanTransition((string) $fromStatus, $toStatus)) {
            if ($ownsTransaction) {
                $pdo->rollBack();
            }
            return false;
        }

        if ($fromStatus !== 'new') {
            throw new LogicException("Переход {$fromStatus} → {$toStatus} пока не реализован (Фаза 3)");
        }

        $itemsStmt = $pdo->prepare('SELECT variant_id, quantity FROM order_items WHERE order_id = ? AND variant_id IS NOT NULL');
        $itemsStmt->execute([$orderId]);

        // Уникальные имена плейсхолдеров — ATTR_EMULATE_PREPARES=false не
        // допускает повтор одного имени в запросе (см. orderCreate()).
        $stockStmt = match ($toStatus) {
            'confirmed' => $pdo->prepare('
                UPDATE product_variants
                SET stock_quantity = stock_quantity - :qty_stock,
                    reserved_quantity = reserved_quantity - :qty_reserved
                WHERE id = :id AND stock_quantity >= :qty_stock_check AND reserved_quantity >= :qty_reserved_check
            '),
            'cancelled' => $pdo->prepare('
                UPDATE product_variants
                SET reserved_quantity = reserved_quantity - :qty_reserved
                WHERE id = :id AND reserved_quantity >= :qty_reserved_check
            '),
        };

        foreach ($itemsStmt->fetchAll() as $item) {
            $quantity = (int) $item['quantity'];
            $params = ['id' => (int) $item['variant_id'], 'qty_reserved' => $quantity, 'qty_reserved_check' => $quantity];
            if ($toStatus === 'confirmed') {
                $params += ['qty_stock' => $quantity, 'qty_stock_check' => $quantity];
            }

            $stockStmt->execute($params);
            if ($stockStmt->rowCount() === 0) {
                throw new RuntimeException("Остаток варианта {$item['variant_id']} не сходится с резервом Заказа {$orderId}");
            }
        }

        $updateStmt = $pdo->prepare('
            UPDATE orders
            SET status = :status, payment_status = COALESCE(:payment_status, payment_status)
            WHERE id = :id
        ');
        $updateStmt->execute(['status' => $toStatus, 'payment_status' => $paymentStatus, 'id' => $orderId]);

        if ($ownsTransaction) {
            $pdo->commit();
        }

        return true;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

/**
 * Лог каждого вызова платёжного callback'а (php.md: логировать все
 * вебхуки с id Заказа) — вне зависимости от исхода.
 */
function orderPaymentLogCreate(int $orderId, string $provider, bool $signatureValid, string $payload): void
{
    $stmt = getPdo()->prepare('
        INSERT INTO payment_logs (order_id, provider, signature_valid, payload)
        VALUES (:order_id, :provider, :signature_valid, :payload)
    ');
    $stmt->execute([
        'order_id'        => $orderId,
        'provider'        => $provider,
        'signature_valid' => $signatureValid ? 1 : 0,
        'payload'         => $payload,
    ]);
}
