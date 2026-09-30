<?php

declare(strict_types=1);

/**
 * Модель Заказа — только SQL через PDO, возвращает массивы (php.md).
 * Денежные правила и адрес — в Core/Order.php. phase-2.md, Таск 5:
 * FR-CHK-004/007, FR-AUTH-002, BR-003.
 *
 * orderCreateFromRows() — единственное место, где создаётся Заказ (чекаут
 * — orderCreate(), персонал — orderCreateManual()): одна транзакция на
 * идемпотентность + автоаккаунт + резерв остатка + order_items + очистку
 * корзины (general.md: многошаговая атомарная запись — одна Model-функция,
 * одна транзакция).
 */

/**
 * Оформление на сайте: корзина владельца → ядро создания Заказа.
 *
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

    return orderCreateFromRows(
        $cartRows,
        $sessionUserId,
        $contact,
        $deliveryMethod,
        $deliveryAddress,
        $paymentMethod,
        $customerNote,
        $checkoutToken,
        null,
        $paymentMethod === 'cash_or_card_on_delivery',
        static function () use ($owner): void {
            cartClearForOwner($owner);
        }
    );
}

/**
 * Ручное создание Заказа персоналом (FR-ORD-003): Варианты по id, цена и
 * остаток берутся из БД. Заказ всегда остаётся в `new` с резервом —
 * автоподтверждение «при получении» (Q-032) не применяется.
 *
 * @param array<int, array{variant_id: int, quantity: int}> $lines
 * @param array{name: string, phone: string, email: string} $contact
 * @return array{status: 'created', order_id: int, new_account: array{name: string, email: string, password: string}|null}
 *       | array{status: 'exists', order_id: int}
 *       | array{status: 'unavailable', product_name: string}
 *       | array{status: 'variant_not_found'}
 */
function orderCreateManual(
    int $staffUserId,
    array $lines,
    array $contact,
    string $deliveryMethod,
    ?string $deliveryAddress,
    string $paymentMethod,
    ?string $customerNote,
    string $checkoutToken
): array {
    $quantities = [];
    foreach ($lines as $line) {
        $variantId = (int) $line['variant_id'];
        $quantities[$variantId] = ($quantities[$variantId] ?? 0) + (int) $line['quantity'];
    }

    $variants = productActiveVariantsByIds(array_keys($quantities));
    $rows = [];
    foreach ($quantities as $variantId => $quantity) {
        if (!isset($variants[$variantId]) || $quantity > ORDER_ITEM_MAX_QUANTITY) {
            return ['status' => 'variant_not_found'];
        }
        $rows[] = $variants[$variantId] + ['quantity' => $quantity];
    }

    $existing = orderFindByCheckoutToken($checkoutToken);
    if ($existing !== null) {
        return ['status' => 'exists', 'order_id' => (int) $existing['id']];
    }

    return orderCreateFromRows(
        $rows,
        null,
        $contact,
        $deliveryMethod,
        $deliveryAddress,
        $paymentMethod,
        $customerNote,
        $checkoutToken,
        $staffUserId,
        false,
        null
    );
}

/**
 * Ядро создания Заказа по списку позиций — единственная транзакция на
 * идемпотентность + резолв Покупателя + резерв остатка + order_items.
 * Общее для чекаута и ручного создания.
 *
 * $rows — строки вида cartItemsForOwner(): variant_id, sku, name, price,
 * discount_price, quantity. $beforeCommit выполняется внутри транзакции
 * после резерва (очистка корзины чекаута).
 *
 * @param array<int, array<string, mixed>> $rows
 * @param array{name: string, phone: string, email: string} $contact
 * @return array{status: 'created', order_id: int, new_account: array{name: string, email: string, password: string}|null}
 *       | array{status: 'exists', order_id: int}
 *       | array{status: 'unavailable', product_name: string}
 */
function orderCreateFromRows(
    array $rows,
    ?int $sessionUserId,
    array $contact,
    string $deliveryMethod,
    ?string $deliveryAddress,
    string $paymentMethod,
    ?string $customerNote,
    string $checkoutToken,
    ?int $createdByUserId,
    bool $autoConfirm,
    ?callable $beforeCommit
): array {
    $lines = [];
    foreach ($rows as $row) {
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
                user_id, created_by_user_id, status, payment_status, delivery_method, payment_method,
                delivery_cost, delivery_address, contact_name, contact_phone, contact_email,
                customer_note, checkout_token, reserved_until, total
            ) VALUES (
                :user_id, :created_by_user_id, \'new\', \'unpaid\', :delivery_method, :payment_method,
                :delivery_cost, :delivery_address, :contact_name, :contact_phone, :contact_email,
                :customer_note, :checkout_token, DATE_ADD(NOW(), INTERVAL :reserve_minutes MINUTE), :total
            )
        ');
        $stmt->execute([
            'user_id'            => $userId,
            'created_by_user_id' => $createdByUserId,
            'delivery_method'    => $deliveryMethod,
            'payment_method'     => $paymentMethod,
            'delivery_cost'      => $deliveryCost,
            'delivery_address'   => $deliveryAddress,
            'contact_name'       => $contact['name'],
            'contact_phone'      => $contact['phone'],
            'contact_email'      => $contact['email'],
            'customer_note'      => $customerNote,
            'checkout_token'     => $checkoutToken,
            'reserve_minutes'    => ORDER_RESERVE_MINUTES,
            'total'              => $total,
        ]);
        $orderId = (int) $pdo->lastInsertId();

        $variantAttributes = productVariantAttributesForVariants(array_map(
            static fn (array $row): int => (int) $row['variant_id'],
            $rows
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

        foreach ($rows as $row) {
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
        if ($autoConfirm && !orderTransition($orderId, 'confirmed')) {
            throw new RuntimeException("Не удалось подтвердить Заказ {$orderId} при создании");
        }

        if ($beforeCommit !== null) {
            $beforeCommit();
        }

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
        SELECT id, product_name, variant_label, price, quantity
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
 * Действие с остатком определяет orderStockAction(): списание при
 * `new → confirmed`, снятие резерва при `new → cancelled`, возврат в
 * остаток при отмене из `confirmed` и дальше; остальное остаток не
 * трогает. Каждый переход пишет `status_changed_at`.
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

        $stockAction = orderStockAction((string) $fromStatus, $toStatus);

        // Уникальные имена плейсхолдеров — ATTR_EMULATE_PREPARES=false не
        // допускает повтор одного имени в запросе (см. orderCreate()).
        $stockStmt = match ($stockAction) {
            'stock_deduct' => $pdo->prepare('
                UPDATE product_variants
                SET stock_quantity = stock_quantity - :qty_stock,
                    reserved_quantity = reserved_quantity - :qty_reserved
                WHERE id = :id AND stock_quantity >= :qty_stock_check AND reserved_quantity >= :qty_reserved_check
            '),
            'reserve_release' => $pdo->prepare('
                UPDATE product_variants
                SET reserved_quantity = reserved_quantity - :qty_reserved
                WHERE id = :id AND reserved_quantity >= :qty_reserved_check
            '),
            // Возврат не может уйти в минус — условие в WHERE не нужно;
            // rowCount() = 0 здесь значит только «Варианта нет».
            'stock_restore' => $pdo->prepare('
                UPDATE product_variants
                SET stock_quantity = stock_quantity + :qty_stock
                WHERE id = :id
            '),
            'none' => null,
        };

        if ($stockStmt !== null) {
            $itemsStmt = $pdo->prepare('SELECT variant_id, quantity FROM order_items WHERE order_id = ? AND variant_id IS NOT NULL');
            $itemsStmt->execute([$orderId]);

            foreach ($itemsStmt->fetchAll() as $item) {
                $quantity = (int) $item['quantity'];
                $params = match ($stockAction) {
                    'stock_deduct'    => ['id' => (int) $item['variant_id'], 'qty_stock' => $quantity, 'qty_reserved' => $quantity,
                        'qty_stock_check' => $quantity, 'qty_reserved_check' => $quantity],
                    'reserve_release' => ['id' => (int) $item['variant_id'], 'qty_reserved' => $quantity, 'qty_reserved_check' => $quantity],
                    'stock_restore'   => ['id' => (int) $item['variant_id'], 'qty_stock' => $quantity],
                };

                $stockStmt->execute($params);
                if ($stockStmt->rowCount() === 0) {
                    throw new RuntimeException("Остаток варианта {$item['variant_id']} не сходится с резервом Заказа {$orderId}");
                }
            }
        }

        $updateStmt = $pdo->prepare('
            UPDATE orders
            SET status = :status,
                payment_status = COALESCE(:payment_status, payment_status),
                status_changed_at = NOW()
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
 * Заказы `new` с истёкшим сроком резерва (BR-003) — кандидаты на отмену.
 * Только id: сам переход и повторную проверку статуса под блокировкой
 * делает orderTransition().
 *
 * @return array<int, int>
 */
function orderFindExpiredIds(): array
{
    $stmt = getPdo()->query("
        SELECT id
        FROM orders
        WHERE status = 'new' AND reserved_until < NOW()
        ORDER BY id ASC
    ");

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
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

/**
 * Страница списка Заказов для админки (FR-ORD-002): от новых к старым,
 * с числом Позиций. $status = null — без фильтра. Индексы —
 * idx_orders_status / idx_orders_created.
 *
 * @return array<int, array<string, mixed>>
 */
function orderListForAdmin(?string $status, int $limit, int $offset): array
{
    $sql = '
        SELECT
            o.id, o.status, o.delivery_method, o.payment_method, o.payment_status,
            o.contact_name, o.contact_phone, o.total, o.created_at,
            (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS items_count
        FROM orders o
    ';
    $params = [];

    if ($status !== null) {
        $sql .= ' WHERE o.status = :status';
        $params[':status'] = $status;
    }

    $sql .= ' ORDER BY o.created_at DESC, o.id DESC LIMIT :limit OFFSET :offset';

    $stmt = getPdo()->prepare($sql);
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function orderCountForAdmin(?string $status): int
{
    if ($status === null) {
        return (int) getPdo()->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    }

    $stmt = getPdo()->prepare('SELECT COUNT(*) FROM orders WHERE status = ?');
    $stmt->execute([$status]);

    return (int) $stmt->fetchColumn();
}

/**
 * Карточка Заказа для админки (FR-ORD-001) — всё, что отдаёт
 * orderFindById(), плюс служебные поля.
 *
 * @return array<string, mixed>|null
 */
function orderFindForAdmin(int $id): ?array
{
    $stmt = getPdo()->prepare('
        SELECT
            id, user_id, status, payment_status, delivery_method, payment_method,
            delivery_cost, delivery_address, contact_name, contact_phone, contact_email,
            customer_note, total, created_at, reserved_until, status_changed_at,
            amocrm_id
        FROM orders
        WHERE id = ?
        LIMIT 1
    ');
    $stmt->execute([$id]);

    $row = $stmt->fetch();
    return $row !== false ? $row : null;
}

/**
 * Отметка «оплачено при получении» (FR-PAY-002): только payment_status,
 * `orders.status` не меняется. false — Заказ не найден, оплата не «при
 * получении», уже оплачен или отменён.
 */
function orderMarkPaid(int $orderId): bool
{
    $stmt = getPdo()->prepare("
        UPDATE orders
        SET payment_status = 'paid'
        WHERE id = :id
          AND payment_method = 'cash_or_card_on_delivery'
          AND payment_status = 'unpaid'
          AND status <> 'cancelled'
    ");
    $stmt->execute(['id' => $orderId]);

    return $stmt->rowCount() > 0;
}

/** payment_status = refunded после успешного возврата денег (FR-PAY-005). */
function orderMarkRefunded(int $orderId): bool
{
    $stmt = getPdo()->prepare("
        UPDATE orders
        SET payment_status = 'refunded'
        WHERE id = :id AND payment_status = 'paid'
    ");
    $stmt->execute(['id' => $orderId]);

    return $stmt->rowCount() > 0;
}

/** Идентификатор сделки заглушки AmoCRM (ADR-023) и время «синхронизации». */
function orderSetAmoCrm(int $orderId, string $amocrmId): void
{
    $stmt = getPdo()->prepare('
        UPDATE orders
        SET amocrm_id = :amocrm_id, amocrm_synced_at = NOW()
        WHERE id = :id
    ');
    $stmt->execute(['amocrm_id' => $amocrmId, 'id' => $orderId]);
}

/**
 * Правка Позиций Заказа одной транзакцией (FR-ORD-004, FR-ORD-005).
 * $change['action']:
 *  - add    — sku, quantity: Вариант по артикулу; если такая Позиция уже
 *             есть — количество складывается, цена Позиции остаётся прежней;
 *  - update — item_id, quantity, price;
 *  - remove — item_id.
 *
 * Строка Заказа блокируется FOR UPDATE, статус проверяется под
 * блокировкой. Остаток: в `new` меняется резерв, в `confirmed`/`assembled`
 * Списание уже было — меняется stock_quantity. Увеличение — условный
 * UPDATE по доступности (BR-004), уменьшение возвращает количество.
 * orders.status не трогается. Пересчитывает delivery_cost и total (BR-006).
 * Для Заказа, оплаченного на сайте картой, рост итога отклоняется —
 * доплата в демо не поддерживается.
 *
 * @param array<string, mixed> $change
 * @return array{status: 'ok', old_total: string, new_total: string}
 *       | array{status: 'not_found'|'not_editable'|'item_not_found'|'variant_not_found'|'last_item'|'surcharge_denied'}
 *       | array{status: 'unavailable', product_name: string}
 */
function orderEditItems(int $orderId, array $change): array
{
    $pdo = getPdo();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('
            SELECT status, delivery_method, payment_method, payment_status, total
            FROM orders
            WHERE id = ?
            FOR UPDATE
        ');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if ($order === false) {
            $pdo->rollBack();
            return ['status' => 'not_found'];
        }

        if (!orderIsEditable((string) $order['status'])) {
            $pdo->rollBack();
            return ['status' => 'not_editable'];
        }

        $itemsStmt = $pdo->prepare('
            SELECT id, variant_id, price, quantity
            FROM order_items
            WHERE order_id = ?
            ORDER BY id ASC
        ');
        $itemsStmt->execute([$orderId]);
        $items = $itemsStmt->fetchAll();

        // Вариант, чей остаток меняется (null — Вариант удалён, остаток не
        // трогаем), и на сколько единиц: > 0 — берём, < 0 — возвращаем.
        $stockVariantId = null;
        $delta = 0;

        if ($change['action'] === 'add') {
            $variantStmt = $pdo->prepare('
                SELECT v.id, v.sku, v.price, v.discount_price, p.name
                FROM product_variants v
                JOIN products p ON p.id = v.product_id
                WHERE v.sku = ? AND v.is_active = 1 AND p.is_active = 1
                LIMIT 1
            ');
            $variantStmt->execute([(string) $change['sku']]);
            $variant = $variantStmt->fetch();

            if ($variant === false) {
                $pdo->rollBack();
                return ['status' => 'variant_not_found'];
            }

            $stockVariantId = (int) $variant['id'];
            $delta = (int) $change['quantity'];

            // Эксклюзивная блокировка Варианта до INSERT в order_items: иначе FK-проверка
            // берёт shared-блокировку, и два параллельных add одного Варианта уходят в deadlock.
            $pdo->prepare('SELECT id FROM product_variants WHERE id = ? FOR UPDATE')->execute([$stockVariantId]);

            $existingIndex = null;
            foreach ($items as $index => $item) {
                if ($item['variant_id'] !== null && (int) $item['variant_id'] === $stockVariantId) {
                    $existingIndex = $index;
                    break;
                }
            }

            if ($existingIndex !== null) {
                $items[$existingIndex]['quantity'] = (int) $items[$existingIndex]['quantity'] + $delta;
                $pdo->prepare('UPDATE order_items SET quantity = :quantity WHERE id = :id AND order_id = :order_id')
                    ->execute([
                        'quantity' => $items[$existingIndex]['quantity'],
                        'id'       => (int) $items[$existingIndex]['id'],
                        'order_id' => $orderId,
                    ]);
            } else {
                $attributes = productVariantAttributesForVariants([$stockVariantId])[$stockVariantId] ?? [];
                $price = orderLineTotal(
                    (string) $variant['price'],
                    $variant['discount_price'] !== null ? (string) $variant['discount_price'] : null,
                    1
                );

                $pdo->prepare('
                    INSERT INTO order_items (order_id, variant_id, product_name, variant_label, price, quantity)
                    VALUES (:order_id, :variant_id, :product_name, :variant_label, :price, :quantity)
                ')->execute([
                    'order_id'      => $orderId,
                    'variant_id'    => $stockVariantId,
                    'product_name'  => (string) $variant['name'],
                    'variant_label' => $attributes !== [] ? implode(', ', $attributes) : (string) $variant['sku'],
                    'price'         => $price,
                    'quantity'      => $delta,
                ]);
                $items[] = ['id' => (int) $pdo->lastInsertId(), 'variant_id' => $stockVariantId, 'price' => $price, 'quantity' => $delta];
            }
        } elseif ($change['action'] === 'update' || $change['action'] === 'remove') {
            $index = null;
            foreach ($items as $i => $item) {
                if ((int) $item['id'] === (int) $change['item_id']) {
                    $index = $i;
                    break;
                }
            }

            if ($index === null) {
                $pdo->rollBack();
                return ['status' => 'item_not_found'];
            }

            $item = $items[$index];
            $stockVariantId = $item['variant_id'] !== null ? (int) $item['variant_id'] : null;

            if ($change['action'] === 'remove') {
                if (count($items) === 1) {
                    $pdo->rollBack();
                    return ['status' => 'last_item'];
                }

                $pdo->prepare('DELETE FROM order_items WHERE id = :id AND order_id = :order_id')
                    ->execute(['id' => (int) $item['id'], 'order_id' => $orderId]);
                unset($items[$index]);
                $delta = -(int) $item['quantity'];
            } else {
                $items[$index]['quantity'] = (int) $change['quantity'];
                $items[$index]['price'] = (string) $change['price'];
                $pdo->prepare('UPDATE order_items SET quantity = :quantity, price = :price WHERE id = :id AND order_id = :order_id')
                    ->execute([
                        'quantity' => $items[$index]['quantity'],
                        'price'    => $items[$index]['price'],
                        'id'       => (int) $item['id'],
                        'order_id' => $orderId,
                    ]);
                $delta = (int) $change['quantity'] - (int) $item['quantity'];
            }
        } else {
            throw new InvalidArgumentException('Неизвестное действие правки Заказа');
        }

        if ($stockVariantId !== null && $delta !== 0) {
            $reserveMode = $order['status'] === 'new';
            $column = $reserveMode ? 'reserved_quantity' : 'stock_quantity';
            $qty = abs($delta);

            // Уникальные имена плейсхолдеров — см. orderCreate().
            if ($delta > 0) {
                $sign = $reserveMode ? '+' : '-';
                $stockStmt = $pdo->prepare("
                    UPDATE product_variants
                    SET {$column} = {$column} {$sign} :qty
                    WHERE id = :id AND stock_quantity - reserved_quantity >= :qty_check
                ");
                $stockStmt->execute(['qty' => $qty, 'id' => $stockVariantId, 'qty_check' => $qty]);
            } elseif ($reserveMode) {
                $stockStmt = $pdo->prepare('
                    UPDATE product_variants
                    SET reserved_quantity = reserved_quantity - :qty
                    WHERE id = :id AND reserved_quantity >= :qty_check
                ');
                $stockStmt->execute(['qty' => $qty, 'id' => $stockVariantId, 'qty_check' => $qty]);
            } else {
                $stockStmt = $pdo->prepare('
                    UPDATE product_variants
                    SET stock_quantity = stock_quantity + :qty
                    WHERE id = :id
                ');
                $stockStmt->execute(['qty' => $qty, 'id' => $stockVariantId]);
            }

            if ($stockStmt->rowCount() === 0) {
                if ($delta < 0) {
                    throw new RuntimeException("Остаток варианта {$stockVariantId} не сходится с Заказом {$orderId}");
                }

                $nameStmt = $pdo->prepare('
                    SELECT p.name
                    FROM product_variants v
                    JOIN products p ON p.id = v.product_id
                    WHERE v.id = ?
                ');
                $nameStmt->execute([$stockVariantId]);
                $pdo->rollBack();

                return ['status' => 'unavailable', 'product_name' => (string) $nameStmt->fetchColumn()];
            }
        }

        $totals = orderRecalculateTotals(
            array_map(
                static fn (array $item): array => ['price' => (string) $item['price'], 'quantity' => (int) $item['quantity']],
                array_values($items)
            ),
            (string) $order['delivery_method'],
            DELIVERY_FREE_THRESHOLD,
            DELIVERY_COURIER_COST
        );
        $oldTotal = (string) $order['total'];

        if (
            $order['payment_method'] === 'card_online'
            && $order['payment_status'] === 'paid'
            && orderMoneyToKopecks($totals['total']) > orderMoneyToKopecks($oldTotal)
        ) {
            $pdo->rollBack();
            return ['status' => 'surcharge_denied'];
        }

        $pdo->prepare('UPDATE orders SET delivery_cost = :delivery_cost, total = :total WHERE id = :id')
            ->execute(['delivery_cost' => $totals['delivery_cost'], 'total' => $totals['total'], 'id' => $orderId]);

        $pdo->commit();

        return ['status' => 'ok', 'old_total' => $oldTotal, 'new_total' => $totals['total']];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}
