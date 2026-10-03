<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\AmoCrm;
use App\Services\OrderCancellation;
use App\Services\Payment\YooMoneyStubGateway;

/**
 * Заказы в админке — /admin/orders, /admin/orders/{id} (phase-3.md,
 * Таск 2; FR-ORD-001, FR-ORD-002) и действия над Заказом (Таск 3;
 * FR-ORD-006, FR-PAY-002, FR-PAY-005). Доступ — `shift_admin`/`owner`;
 * Специалист Заказов не видит (FR-ORD-003). Статус меняется только через
 * orderTransition() / OrderCancellation, все POST — CSRF + redirect().
 */
final class OrderController
{
    private const PER_PAGE = 20;
    private const STATUS_REJECTED_ERROR = 'Этот переход статуса сейчас недоступен.';
    private const ACTION_FAILED_ERROR = 'Не удалось выполнить действие. Попробуйте ещё раз.';
    private const REFUND_FAILED_ERROR = 'Заказ отменён, но возврат денег не прошёл — проверьте платёж вручную.';
    private const SKU_MAX_LENGTH = 64;
    private const ITEM_INPUT_INVALID_ERROR = 'Проверьте артикул, количество и цену — данные некорректны.';
    private const ITEM_EDIT_REJECTED_ERROR = 'Состав Заказа в этом статусе изменить нельзя.';
    private const PARTIAL_REFUND_FAILED_ERROR = 'Состав обновлён, но возврат разницы не прошёл — проверьте платёж вручную.';
    private const VARIANT_SEARCH_MIN_LENGTH = 2;
    private const VARIANT_SEARCH_MAX_LENGTH = 64;
    private const VARIANT_SEARCH_LIMIT = 10;
    private const MAX_ORDER_LINES = 50;
    private const CREATE_VALIDATION_ERROR = 'Проверьте форму: контакты, способ получения и оплаты, адрес для курьера и хотя бы одна Позиция обязательны.';
    private const CREATE_VARIANT_ERROR = 'Один из выбранных Вариантов не найден или снят с продажи.';
    private const MARKETPLACE_ORDER_ERROR = 'Заказ с площадки только для чтения: статусом, оплатой и составом управляет площадка.';
    private const MARK_PAID_REJECTED_ERROR ='Отметить оплату нельзя: способ оплаты другой, Заказ уже оплачен или отменён.';

    public function index(): void
    {
        requireRole('shift_admin', 'owner');

        $statusInput = input('status', '');
        $status = is_string($statusInput) && array_key_exists($statusInput, ORDER_STATUS_TRANSITIONS)
            ? $statusInput
            : null;

        $sourceInput = input('source', '');
        $source = is_string($sourceInput) && array_key_exists($sourceInput, ORDER_SOURCE_LABELS)
            ? $sourceInput
            : null;

        $total = orderCountForAdmin($status, $source);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        $pageInput = input('page', '1');
        $page = is_string($pageInput) && ctype_digit($pageInput)
            ? min(max(1, (int) $pageInput), $totalPages)
            : 1;

        $role = (string) $_SESSION['user_role'];

        render('admin/orders', [
            'pageTitle'  => 'Заказы — PetPark',
            'roleLabel'  => adminRoleLabel($role),
            'homeUrl'    => homePathForRole($role),
            'userRole'   => $role,
            'orders'     => orderListForAdmin($status, self::PER_PAGE, ($page - 1) * self::PER_PAGE, $source),
            'status'     => $status,
            'source'     => $source,
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
        ]);
    }

    public function createForm(): void
    {
        requireRole('shift_admin', 'owner');

        $conversation = $this->requestedConversation(input('conversation', ''));
        if ($conversation === false) {
            http_response_code(404);
            render('errors/404');
            return;
        }
        if ($conversation !== null && $conversation['order_id'] !== null) {
            setFlash('success', 'Из этого Обращения Заказ уже создан.');
            redirect('/admin/orders/' . (int) $conversation['order_id']);
        }

        $source = input('source', '') === 'draft' ? 'draft' : 'manual';
        $old = $this->readOldInput();
        $prefill = $old === null && $conversation !== null
            ? $this->conversationPrefill($conversation, $source)
            : null;
        $oldLines = [];

        $formLines = $old['lines'] ?? $prefill['lines'] ?? [];
        if ($formLines !== []) {
            $variants = productActiveVariantsByIds(array_map(
                static fn (array $line): int => (int) $line['variant_id'],
                $formLines
            ));

            foreach ($formLines as $line) {
                $variant = $variants[(int) $line['variant_id']] ?? null;
                if ($variant !== null) {
                    $oldLines[] = [
                        'variant_id' => (int) $line['variant_id'],
                        'quantity'   => (int) $line['quantity'],
                        'name'       => (string) $variant['name'],
                        'sku'        => (string) $variant['sku'],
                        'price'      => orderLineTotal(
                            (string) $variant['price'],
                            $variant['discount_price'] !== null ? (string) $variant['discount_price'] : null,
                            1
                        ),
                    ];
                }
            }
        }

        $role = (string) $_SESSION['user_role'];

        render('admin/order-create', [
            'pageTitle'     => 'Новый заказ — PetPark',
            'roleLabel'     => adminRoleLabel($role),
            'homeUrl'       => homePathForRole($role),
            'userRole'      => $role,
            'form'          => $old['fields'] ?? $prefill['fields'] ?? [],
            'conversationId' => $conversation !== null ? (int) $conversation['id'] : null,
            'source'        => $source,
            'oldLines'      => $oldLines,
            'checkoutToken' => bin2hex(random_bytes(ORDER_CHECKOUT_TOKEN_BYTES)),
            'freeThreshold' => DELIVERY_FREE_THRESHOLD,
            'courierCost'   => DELIVERY_COURIER_COST,
            'error'         => getFlash('error'),
        ]);
    }

    /** Поиск Вариантов для формы ручного Заказа — JSON для admin-order-create.js. */
    public function searchVariants(): void
    {
        requireRole('shift_admin', 'owner');
        // Эндпоинт только читает: снимаем блокировку файла сессии, иначе
        // частые запросы поиска выстраиваются в очередь и подвешивают страницу.
        session_write_close();

        $query = trim((string) input('q', ''));
        $variants = [];

        if (mb_strlen($query) >= self::VARIANT_SEARCH_MIN_LENGTH && mb_strlen($query) <= self::VARIANT_SEARCH_MAX_LENGTH) {
            foreach (productSearchVariants($query, self::VARIANT_SEARCH_LIMIT) as $row) {
                $variants[] = [
                    'variant_id' => (int) $row['variant_id'],
                    'name'       => (string) $row['name'],
                    'label'      => (string) $row['variant_label'],
                    'sku'        => (string) $row['sku'],
                    'price'      => orderLineTotal(
                        (string) $row['price'],
                        $row['discount_price'] !== null ? (string) $row['discount_price'] : null,
                        1
                    ),
                    'available'  => max(0, (int) $row['stock_quantity'] - (int) $row['reserved_quantity']),
                ];
            }
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['variants' => $variants], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Ручное создание Заказа (FR-ORD-003): валидация здесь, транзакция — orderCreateManual(). */
    public function store(): void
    {
        requireRole('shift_admin', 'owner');
        requireCsrf();

        $conversation = $this->requestedConversation(input('conversation_id', ''));
        if ($conversation === false) {
            http_response_code(404);
            render('errors/404');
            return;
        }
        $source = input('source', '') === 'draft' ? 'draft' : 'manual';
        $formUrl = $conversation !== null
            ? '/admin/orders/new?conversation=' . (int) $conversation['id'] . '&source=' . $source
            : '/admin/orders/new';

        $token = input('checkout_token');
        $checkoutToken = is_string($token) && preg_match(ORDER_CHECKOUT_TOKEN_PATTERN, $token) === 1 ? $token : null;

        if ($checkoutToken === null) {
            setFlash('error', self::CREATE_VALIDATION_ERROR);
            redirect($formUrl);
        }

        $existing = orderFindByCheckoutToken($checkoutToken);
        if ($existing !== null) {
            redirect('/admin/orders/' . (int) $existing['id']);
        }
        if ($conversation !== null && $conversation['order_id'] !== null) {
            setFlash('success', 'Из этого Обращения Заказ уже создан.');
            redirect('/admin/orders/' . (int) $conversation['order_id']);
        }

        $fields = [
            'contact_name'       => trim((string) input('contact_name')),
            'contact_phone'      => trim((string) input('contact_phone')),
            'contact_email'      => trim((string) input('contact_email')),
            'delivery_method'    => (string) input('delivery_method'),
            'payment_method'     => (string) input('payment_method'),
            'customer_note'      => trim((string) input('customer_note')),
            'delivery_street'    => trim((string) input('delivery_street')),
            'delivery_house'     => trim((string) input('delivery_house')),
            'delivery_apartment' => trim((string) input('delivery_apartment')),
            'delivery_comment'   => trim((string) input('delivery_comment')),
        ];
        $lines = $this->parseOrderLines(input('items', []));

        $isValid = $lines !== null
            && $fields['contact_name'] !== '' && mb_strlen($fields['contact_name']) <= 150
            && $fields['contact_phone'] !== '' && mb_strlen($fields['contact_phone']) <= 20
            && $fields['contact_email'] !== '' && mb_strlen($fields['contact_email']) <= 255
            && filter_var($fields['contact_email'], FILTER_VALIDATE_EMAIL) !== false
            && in_array($fields['delivery_method'], ['pickup', 'courier'], true)
            && in_array($fields['payment_method'], ['card_online', 'cash_or_card_on_delivery'], true)
            && mb_strlen($fields['customer_note']) <= 500
            && ($fields['delivery_method'] !== 'courier' || ($fields['delivery_street'] !== '' && $fields['delivery_house'] !== ''));

        if (!$isValid) {
            $this->rememberOldInput($fields, $lines ?? []);
            setFlash('error', self::CREATE_VALIDATION_ERROR);
            redirect($formUrl);
        }

        $deliveryAddress = $fields['delivery_method'] === 'courier'
            ? orderBuildDeliveryAddress(
                SHOP_CITY,
                $fields['delivery_street'],
                $fields['delivery_house'],
                $fields['delivery_apartment'],
                $fields['delivery_comment']
            )
            : null;

        $beforeCommit = null;
        if ($conversation !== null) {
            $outcome = orderDraftOutcome($this->draftLines($conversation['order_draft']), $lines, $source);
            $conversationId = (int) $conversation['id'];
            $beforeCommit = static fn (int $orderId) => conversationAttachOrder($conversationId, $orderId, $outcome);
        }

        try {
            $result = orderCreateManual(
                (int) $_SESSION['user_id'],
                $lines,
                ['name' => $fields['contact_name'], 'phone' => $fields['contact_phone'], 'email' => $fields['contact_email']],
                $fields['delivery_method'],
                $deliveryAddress,
                $fields['payment_method'],
                $fields['customer_note'] !== '' ? $fields['customer_note'] : null,
                $checkoutToken,
                $beforeCommit
            );
        } catch (\Throwable $e) {
            logException($e, ['action' => 'order_create_manual']);
            $this->rememberOldInput($fields, $lines);
            setFlash('error', self::ACTION_FAILED_ERROR);
            redirect($formUrl);
        }

        if ($result['status'] === 'variant_not_found' || $result['status'] === 'unavailable') {
            $this->rememberOldInput($fields, $lines);
            setFlash('error', $result['status'] === 'unavailable'
                ? 'Недостаточно товара «' . $result['product_name'] . '» в наличии.'
                : self::CREATE_VARIANT_ERROR);
            redirect($formUrl);
        }

        setFlash('success', 'Заказ создан, резерв товара начат.');
        redirect('/admin/orders/' . $result['order_id']);
    }

    /**
     * Обращение из параметра запроса: null — параметра нет, false — Обращение
     * не найдено или его Канал выключен (FR-CHANNELS-005).
     *
     * @return array<string, mixed>|false|null
     */
    private function requestedConversation(mixed $id): array|false|null
    {
        if ($id === null || $id === '') {
            return null;
        }

        $conversation = is_string($id) && ctype_digit($id) ? conversationFind((int) $id) : null;
        if ($conversation === null || !in_array((string) $conversation['channel'], enabledChannels(CHANNELS_ENABLED), true)) {
            return false;
        }

        return $conversation;
    }

    /**
     * Позиции сохранённого черновика Обращения (variant_id + quantity).
     *
     * @return array<int, array{variant_id: int, quantity: int}>
     */
    private function draftLines(mixed $json): array
    {
        $draft = is_string($json) && $json !== '' ? json_decode($json, true) : null;
        if (!is_array($draft) || !is_array($draft['items'] ?? null)) {
            return [];
        }

        $lines = [];
        foreach ($draft['items'] as $item) {
            if (is_array($item) && isset($item['variant_id'], $item['quantity'])) {
                $lines[] = ['variant_id' => (int) $item['variant_id'], 'quantity' => (int) $item['quantity']];
            }
        }

        return $lines;
    }

    /**
     * Предзаполнение формы Заказа данными Обращения: контакты из БД
     * (опознанный Покупатель, имя отправителя, идентификатор Канала), при
     * `draft` — Позиции черновика. Email Обращение не даёт — вводит администратор.
     *
     * @param array<string, mixed> $conversation
     * @return array{fields: array<string, string>, lines: array<int, array{variant_id: int, quantity: int}>}
     */
    private function conversationPrefill(array $conversation, string $source): array
    {
        $customer = $conversation['user_id'] !== null ? userFindById((int) $conversation['user_id']) : null;
        $identifier = trim((string) ($conversation['contact_identifier'] ?? ''));

        $phone = (string) ($customer['phone'] ?? '');
        if ($phone === '' && $identifier !== '' && normalizePhone($identifier) !== null) {
            $phone = $identifier;
        }

        $name = (string) ($customer['name'] ?? '');
        if ($name === '') {
            $name = trim((string) ($conversation['sender_name'] ?? ''));
        }

        return [
            'fields' => [
                'contact_name'  => mb_substr($name, 0, 150),
                'contact_phone' => mb_substr($phone, 0, 20),
                'contact_email' => (string) ($customer['email'] ?? ''),
            ],
            'lines'  => $source === 'draft' ? $this->draftLines($conversation['order_draft']) : [],
        ];
    }

    /**
     * Позиции из POST `items[N][variant_id|quantity]`; null — ввод некорректен.
     *
     * @return array<int, array{variant_id: int, quantity: int}>|null
     */
    private function parseOrderLines(mixed $raw): ?array
    {
        if (!is_array($raw) || $raw === [] || count($raw) > self::MAX_ORDER_LINES) {
            return null;
        }

        $lines = [];
        foreach ($raw as $row) {
            $variantId = is_array($row) ? ($row['variant_id'] ?? null) : null;
            $quantity = is_array($row) ? ($row['quantity'] ?? null) : null;

            if (
                !is_string($variantId) || !ctype_digit($variantId) || (int) $variantId < 1
                || !is_string($quantity) || !ctype_digit($quantity)
                || (int) $quantity < 1 || (int) $quantity > ORDER_ITEM_MAX_QUANTITY
            ) {
                return null;
            }

            $lines[] = ['variant_id' => (int) $variantId, 'quantity' => (int) $quantity];
        }

        return $lines;
    }

    /**
     * Введённое сохраняется на один показ формы после ошибки — flash хранит
     * только строки, поэтому JSON.
     *
     * @param array<string, string> $fields
     * @param array<int, array{variant_id: int, quantity: int}> $lines
     */
    private function rememberOldInput(array $fields, array $lines): void
    {
        setFlash('order_create_old', (string) json_encode(['fields' => $fields, 'lines' => $lines], JSON_UNESCAPED_UNICODE));
    }

    /** @return array{fields: array<string, string>, lines: array<int, array{variant_id: int, quantity: int}>}|null */
    private function readOldInput(): ?array
    {
        $json = getFlash('order_create_old');
        $data = $json !== null ? json_decode($json, true) : null;

        if (!is_array($data) || !is_array($data['fields'] ?? null) || !is_array($data['lines'] ?? null)) {
            return null;
        }

        return ['fields' => array_map('strval', $data['fields']), 'lines' => $data['lines']];
    }

    public function show(string $id): void
    {
        requireRole('shift_admin', 'owner');

        $order = ctype_digit($id) ? orderFindForAdmin((int) $id) : null;

        if ($order === null) {
            http_response_code(404);
            render('errors/404');
            return;
        }

        $role = (string) $_SESSION['user_role'];

        render('admin/order', [
            'pageTitle' => 'Заказ №' . $order['id'] . ' — PetPark',
            'roleLabel' => adminRoleLabel($role),
            'homeUrl'   => homePathForRole($role),
            'userRole'  => $role,
            'order'     => $order,
            'items'     => orderItemsForOrder((int) $order['id']),
            'transitions' => $this->availableTransitions($order),
            'managedBySite' => orderIsManagedBySite((string) $order['source']),
            'canEditItems' => orderIsManagedBySite((string) $order['source']) && orderIsEditable((string) $order['status']),
            'freeThreshold' => DELIVERY_FREE_THRESHOLD,
            'courierCost'   => DELIVERY_COURIER_COST,
            'success'   => getFlash('success'),
            'error'     => getFlash('error'),
        ]);
    }

    public function changeStatus(string $id): void
    {
        requireRole('shift_admin', 'owner');
        requireCsrf();

        $order = ctype_digit($id) ? orderFindForAdmin((int) $id) : null;

        if ($order === null) {
            http_response_code(404);
            render('errors/404');
            return;
        }

        $orderId = (int) $order['id'];
        $this->rejectMarketplaceOrder($order);
        $target = input('status', '');

        if (!is_string($target) || !in_array($target, $this->availableTransitions($order), true)) {
            setFlash('error', self::STATUS_REJECTED_ERROR);
            redirect('/admin/orders/' . $orderId);
        }

        try {
            if ($target === 'cancelled') {
                $this->cancelOrder($orderId);
            } else {
                $this->transitionOrder($order, $target);
            }
        } catch (\Throwable $e) {
            logException($e, ['order_id' => $orderId, 'target' => $target]);
            setFlash('error', self::ACTION_FAILED_ERROR);
        }

        redirect('/admin/orders/' . $orderId);
    }

    public function markPaid(string $id): void
    {
        requireRole('shift_admin', 'owner');
        requireCsrf();

        $order = ctype_digit($id) ? orderFindForAdmin((int) $id) : null;

        if ($order === null) {
            http_response_code(404);
            render('errors/404');
            return;
        }

        $orderId = (int) $order['id'];
        $this->rejectMarketplaceOrder($order);

        if (orderMarkPaid($orderId)) {
            setFlash('success', 'Оплата при получении отмечена.');
        } else {
            setFlash('error', self::MARK_PAID_REJECTED_ERROR);
        }

        redirect('/admin/orders/' . $orderId);
    }

    /**
     * Правка Позиций Заказа: add / update / remove (FR-ORD-004, FR-ORD-005).
     * Валидация здесь, статус и остаток — под блокировкой в orderEditItems().
     */
    public function editItems(string $id): void
    {
        requireRole('shift_admin', 'owner');
        requireCsrf();

        $order = ctype_digit($id) ? orderFindForAdmin((int) $id) : null;

        if ($order === null) {
            http_response_code(404);
            render('errors/404');
            return;
        }

        $orderId = (int) $order['id'];
        $this->rejectMarketplaceOrder($order);
        $change = $this->parseItemChange();

        if ($change === null) {
            setFlash('error', self::ITEM_INPUT_INVALID_ERROR);
            redirect('/admin/orders/' . $orderId);
        }

        try {
            $result = orderEditItems($orderId, $change);
        } catch (\Throwable $e) {
            logException($e, ['order_id' => $orderId, 'action' => $change['action']]);
            setFlash('error', self::ACTION_FAILED_ERROR);
            redirect('/admin/orders/' . $orderId);
        }

        if ($result['status'] !== 'ok') {
            setFlash('error', match ($result['status']) {
                'unavailable'       => 'Недостаточно товара «' . $result['product_name'] . '» в наличии.',
                'variant_not_found' => 'Вариант с таким артикулом не найден или снят с продажи.',
                'item_not_found'    => 'Такой Позиции в Заказе нет.',
                'last_item'         => 'Нельзя удалить последнюю Позицию — отмените Заказ.',
                'surcharge_denied'  => 'Доплата в демо не поддерживается: итог Заказа, оплаченного картой, не может вырасти.',
                default             => self::ITEM_EDIT_REJECTED_ERROR,
            });
            redirect('/admin/orders/' . $orderId);
        }

        $refundFailed = $this->refundDifference($order, $result['old_total'], $result['new_total']);

        if (($order['amocrm_id'] ?? null) !== null) {
            (new AmoCrm())->updateDeal((string) $order['amocrm_id'], (string) $order['status'], $result['new_total']);
        }

        if ($refundFailed) {
            setFlash('error', self::PARTIAL_REFUND_FAILED_ERROR);
        } else {
            setFlash('success', 'Состав Заказа обновлён.');
        }

        redirect('/admin/orders/' . $orderId);
    }

    /**
     * Разбирает и валидирует POST правки Позиции; null — ввод некорректен.
     *
     * @return array<string, mixed>|null
     */
    private function parseItemChange(): ?array
    {
        $action = input('action', '');
        $itemId = input('item_id', '');
        $quantity = input('quantity', '');
        $price = input('price', '');
        $sku = input('sku', '');

        $quantityValid = is_string($quantity) && ctype_digit($quantity)
            && (int) $quantity >= 1 && (int) $quantity <= ORDER_ITEM_MAX_QUANTITY;
        $itemIdValid = is_string($itemId) && ctype_digit($itemId) && (int) $itemId > 0;

        return match (true) {
            $action === 'add' && $quantityValid && is_string($sku)
                && trim($sku) !== '' && mb_strlen(trim($sku)) <= self::SKU_MAX_LENGTH
                => ['action' => 'add', 'sku' => trim($sku), 'quantity' => (int) $quantity],
            $action === 'update' && $itemIdValid && $quantityValid && $this->normalizePrice($price) !== null
                => ['action' => 'update', 'item_id' => (int) $itemId, 'quantity' => (int) $quantity, 'price' => $this->normalizePrice($price)],
            $action === 'remove' && $itemIdValid
                => ['action' => 'remove', 'item_id' => (int) $itemId],
            default => null,
        };
    }

    /** «1 500,5» / «1500.50» → «1500.50»; null — не сумма или ноль. */
    private function normalizePrice(mixed $price): ?string
    {
        if (!is_string($price)) {
            return null;
        }

        $clean = str_replace([' ', ','], ['', '.'], trim($price));

        if (preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $clean) !== 1) {
            return null;
        }

        $kopecks = orderMoneyToKopecks($clean);

        return $kopecks > 0 ? orderKopecksToMoney($kopecks) : null;
    }

    /**
     * Частичный возврат разницы, если итог Заказа, оплаченного картой,
     * уменьшился (FR-PAY-005). Правка уже сохранена — при отказе шлюза
     * Заказ остаётся изменённым, ошибка в лог и во flash (как в Таске 3).
     *
     * @param array<string, mixed> $order
     * @return bool true — возврат нужен, но не прошёл
     */
    private function refundDifference(array $order, string $oldTotal, string $newTotal): bool
    {
        $difference = orderMoneyToKopecks($oldTotal) - orderMoneyToKopecks($newTotal);

        if ($order['payment_method'] !== 'card_online' || $order['payment_status'] !== 'paid' || $difference <= 0) {
            return false;
        }

        $orderId = (int) $order['id'];
        $amount = orderKopecksToMoney($difference);
        $gateway = new YooMoneyStubGateway(env('PAYMENT_STUB_SECRET'));
        $refunded = false;

        try {
            $refunded = $gateway->refund($orderId, $amount);
        } catch (\Throwable $e) {
            logException($e, ['order_id' => $orderId]);
        }

        orderPaymentLogCreate(
            $orderId,
            $gateway->providerName(),
            true,
            (string) json_encode(['action' => 'refund', 'amount' => $amount, 'success' => $refunded], JSON_UNESCAPED_UNICODE)
        );

        if (!$refunded) {
            logError('Частичный возврат при правке Заказа не прошёл', ['order_id' => $orderId, 'amount' => $amount]);
        }

        return !$refunded;
    }

    /**
     * Переходы из текущего статуса по карте tz.md §6.3, отфильтрованные по
     * способу получения: «Передан курьеру» — только курьерским Заказам,
     * «Готов к выдаче» — только самовывозу. Один список и для кнопок в
     * карточке, и для проверки прямого POST.
     *
     * @param array<string, mixed> $order
     * @return array<int, string>
     */
    private function availableTransitions(array $order): array
    {
        if (!orderIsManagedBySite((string) $order['source'])) {
            return [];
        }

        $allowed = ORDER_STATUS_TRANSITIONS[(string) $order['status']] ?? [];

        return array_values(array_filter($allowed, static fn (string $to): bool => match ($to) {
            'shipped'          => $order['delivery_method'] === 'courier',
            'ready_for_pickup' => $order['delivery_method'] === 'pickup',
            default            => true,
        }));
    }

    /**
     * Заказ с площадки менять нельзя: флеш + возврат в карточку (redirect() завершает запрос).
     *
     * @param array<string, mixed> $order
     */
    private function rejectMarketplaceOrder(array $order): void
    {
        if (!orderIsManagedBySite((string) $order['source'])) {
            setFlash('error', self::MARKETPLACE_ORDER_ERROR);
            redirect('/admin/orders/' . (int) $order['id']);
        }
    }

    /** @param array<string, mixed> $order */
    private function transitionOrder(array $order, string $target): void
    {
        $orderId = (int) $order['id'];

        if (!orderTransition($orderId, $target)) {
            setFlash('error', self::STATUS_REJECTED_ERROR);
            return;
        }

        $amoCrm = new AmoCrm();

        if ($target === 'confirmed') {
            orderSetAmoCrm($orderId, $amoCrm->pushOrder($orderId));
        } elseif (($order['amocrm_id'] ?? null) !== null) {
            $amoCrm->updateDeal((string) $order['amocrm_id'], $target);
        }

        setFlash('success', 'Статус Заказа обновлён.');
    }

    private function cancelOrder(int $orderId): void
    {
        $cancellation = new OrderCancellation(new YooMoneyStubGateway(env('PAYMENT_STUB_SECRET')));

        match ($cancellation->cancel($orderId)) {
            OrderCancellation::RESULT_NOT_ALLOWED   => setFlash('error', self::STATUS_REJECTED_ERROR),
            OrderCancellation::RESULT_REFUND_FAILED => setFlash('error', self::REFUND_FAILED_ERROR),
            OrderCancellation::RESULT_REFUNDED      => setFlash('success', 'Заказ отменён, остаток и деньги возвращены.'),
            OrderCancellation::RESULT_CANCELLED     => setFlash('success', 'Заказ отменён, остаток возвращён.'),
        };
    }
}
