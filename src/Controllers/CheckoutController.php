<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Оформление заказа — /checkout (phase-2.md, Таск 4 — форма; Таск 5 —
 * создание Заказа: FR-CHK-004/007, FR-AUTH-002, BR-003).
 *
 * Два разных одноразовых токена в форме, не один: `form_token`
 * (антибот, generateFormToken()/verifyFormToken(), functions.php) —
 * удаляется из сессии при первой же проверке; `checkout_token`
 * (FR-CHK-007) — отдельное скрытое поле, не привязанное к сессии,
 * идемпотентность проверяется через UNIQUE(checkout_token) в БД
 * (orderCreate(), Models/Order.php). Если использовать form_token и
 * для идемпотентности, законный двойной сабмит (двойной клик) на
 * второй попытке словил бы «бот».
 *
 * Доступ к странице успеха — по номеру Заказа в
 * $_SESSION['checkout_order_ids'] (записывается при создании) либо
 * авторизованному владельцу; иначе 404 (phase-2.md, «Контекст»).
 */
final class CheckoutController
{
    private const MIN_FORM_FILL_SECONDS = 3;
    private const VALIDATION_ERROR = 'Проверьте поля формы — контакты, способ получения и оплаты обязательны.';
    private const BOT_REJECTED_NOTICE = 'Не удалось оформить заказ. Проверьте корзину и попробуйте снова.';
    private const RATE_LIMITED_ERROR = 'Слишком много попыток оформления. Попробуйте через минуту.';

    public function index(): void
    {
        $summary = cartSummarize(cartItemsForOwner(cartOwner()));

        if ($summary['items'] === []) {
            redirect('/cart');
        }

        $contact = ['name' => '', 'phone' => '', 'email' => ''];
        $isCustomer = isAuthenticated() && ($_SESSION['user_role'] ?? null) === 'customer';

        if ($isCustomer) {
            $user = userFindById((int) $_SESSION['user_id']);
            if ($user !== null) {
                $contact = [
                    'name'  => (string) $user['name'],
                    'phone' => (string) ($user['phone'] ?? ''),
                    'email' => (string) $user['email'],
                ];
            }
        }

        $deliveryCost = [
            'pickup'  => orderDeliveryCost('pickup', $summary['subtotal'], DELIVERY_FREE_THRESHOLD, DELIVERY_COURIER_COST),
            'courier' => orderDeliveryCost('courier', $summary['subtotal'], DELIVERY_FREE_THRESHOLD, DELIVERY_COURIER_COST),
        ];

        $total = [];
        foreach ($deliveryCost as $method => $cost) {
            $total[$method] = orderKopecksToMoney(
                orderMoneyToKopecks($summary['subtotal']) + orderMoneyToKopecks($cost)
            );
        }

        render('checkout', [
            'subtotal'      => $summary['subtotal'],
            'deliveryCost'  => $deliveryCost,
            'total'         => $total,
            'contact'       => $contact,
            'isCustomer'    => $isCustomer,
            'checkoutToken' => bin2hex(random_bytes(ORDER_CHECKOUT_TOKEN_BYTES)),
            'formToken'     => generateFormToken('checkout'),
            'notice'        => getFlash('checkout_notice'),
            'error'         => getFlash('checkout_error'),
        ]);
    }

    public function store(): void
    {
        requireCsrf();

        $owner = cartOwner();

        $checkoutToken = $this->normalizeCheckoutToken(input('checkout_token'));
        if ($checkoutToken === null) {
            setFlash('checkout_error', self::VALIDATION_ERROR);
            redirect('/checkout');
        }

        $existing = orderFindByCheckoutToken($checkoutToken);
        if ($existing !== null) {
            redirect('/checkout/success/' . (int) $existing['id']);
        }

        if (tooManyAttempts('checkout', 5, 60)) {
            logWarning('Оформление заказа: превышен лимит попыток');
            setFlash('checkout_error', self::RATE_LIMITED_ERROR);
            redirect('/checkout');
        }
        hitRateLimit('checkout');

        $honeypot = trim((string) input('website'));
        $formToken = input('form_token');
        $tokenValid = verifyFormToken(
            'checkout',
            is_string($formToken) && $formToken !== '' ? $formToken : null,
            self::MIN_FORM_FILL_SECONDS
        );

        if ($honeypot !== '' || !$tokenValid) {
            // Отказ бота не должен раскрывать причину — тот же нейтральный
            // путь, что у других блокировок корзины: товары остаются на
            // месте, никакого Заказа не создаётся (dod-global.md).
            logWarning('Оформление заказа: отклонено как бот', [
                'honeypot_filled' => $honeypot !== '',
                'token_valid'     => $tokenValid,
            ]);
            setFlash('cart_notice', self::BOT_REJECTED_NOTICE);
            redirect('/cart');
        }

        $name = trim((string) input('contact_name'));
        $phone = trim((string) input('contact_phone'));
        $email = trim((string) input('contact_email'));
        $deliveryMethod = (string) input('delivery_method');
        $paymentMethod = (string) input('payment_method');
        $customerNoteRaw = trim((string) input('customer_note'));
        $customerNote = $customerNoteRaw !== '' ? $customerNoteRaw : null;

        $street = trim((string) input('delivery_street'));
        $house = trim((string) input('delivery_house'));
        $apartment = trim((string) input('delivery_apartment'));
        $comment = trim((string) input('delivery_comment'));

        $isValid = $name !== '' && mb_strlen($name) <= 150
            && $phone !== '' && mb_strlen($phone) <= 20
            && $email !== '' && mb_strlen($email) <= 255 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && in_array($deliveryMethod, ['pickup', 'courier'], true)
            && in_array($paymentMethod, ['card_online', 'cash_or_card_on_delivery'], true)
            && ($customerNote === null || mb_strlen($customerNote) <= 500)
            && ($deliveryMethod !== 'courier' || ($street !== '' && $house !== ''));

        if (!$isValid) {
            setFlash('checkout_error', self::VALIDATION_ERROR);
            redirect('/checkout');
        }

        $deliveryAddress = $deliveryMethod === 'courier'
            ? orderBuildDeliveryAddress(SHOP_CITY, $street, $house, $apartment, $comment)
            : null;

        $cartRows = cartItemsForOwner($owner);
        if ($cartRows === []) {
            redirect('/cart');
        }

        $priceChanged = false;
        foreach ($cartRows as $row) {
            $liveUnitPrice = orderLineTotal(
                (string) $row['price'],
                $row['discount_price'] !== null ? (string) $row['discount_price'] : null,
                1
            );

            if (orderMoneyToKopecks($liveUnitPrice) !== orderMoneyToKopecks((string) $row['price_seen'])) {
                cartUpdateItemOnAdd($owner, (int) $row['id'], (int) $row['quantity'], $liveUnitPrice);
                $priceChanged = true;
            }
        }

        if ($priceChanged) {
            setFlash('checkout_notice', 'Цена одного или нескольких товаров изменилась — проверьте обновлённую сумму и подтвердите оформление ещё раз.');
            redirect('/checkout');
        }

        $isCustomer = isAuthenticated() && ($_SESSION['user_role'] ?? null) === 'customer';
        $sessionUserId = $isCustomer ? (int) $_SESSION['user_id'] : null;

        $result = orderCreate(
            $owner,
            $sessionUserId,
            ['name' => $name, 'phone' => $phone, 'email' => $email],
            $deliveryMethod,
            $deliveryAddress,
            $paymentMethod,
            $customerNote,
            $checkoutToken
        );

        match ($result['status']) {
            'empty' => redirect('/cart'),
            'exists' => redirect('/checkout/success/' . $result['order_id']),
            'unavailable' => $this->rejectUnavailable($result['product_name']),
            'created' => $this->finishCreated($result),
        };
    }

    public function success(string $id): void
    {
        $orderId = (int) $id;
        $order = $orderId > 0 ? orderFindById($orderId) : null;

        if ($order === null || !$this->canViewOrder($order)) {
            http_response_code(404);
            render('errors/404');
            return;
        }

        render('order-success', [
            'order' => $order,
            'items' => orderItemsForOrder($orderId),
        ]);
    }

    private function rejectUnavailable(string $productName): never
    {
        setFlash('checkout_error', sprintf(
            'Товар «%s» закончился, пока вы оформляли заказ. Проверьте корзину и попробуйте снова.',
            $productName
        ));
        redirect('/checkout');
    }

    /**
     * @param array{status: 'created', order_id: int, new_account: array{name: string, email: string, password: string}|null} $result
     */
    private function finishCreated(array $result): never
    {
        if ($result['new_account'] !== null) {
            try {
                sendNewCustomerAccountEmail(
                    $result['new_account']['email'],
                    $result['new_account']['name'],
                    $result['new_account']['password']
                );
            } catch (\Throwable $e) {
                // Заказ уже создан — ошибку SMTP не показываем покупателю
                // (php.md: friendly-сообщение юзеру, полный трейс в лог).
                logException($e, ['order_id' => $result['order_id']]);
            }
        }

        clearRateLimit('checkout');
        ensureSessionStarted();
        $_SESSION['checkout_order_ids'][] = $result['order_id'];

        redirect('/checkout/success/' . $result['order_id']);
    }

    private function normalizeCheckoutToken(mixed $raw): ?string
    {
        return is_string($raw) && preg_match(ORDER_CHECKOUT_TOKEN_PATTERN, $raw) === 1 ? $raw : null;
    }

    /**
     * @param array<string, mixed> $order
     */
    private function canViewOrder(array $order): bool
    {
        ensureSessionStarted();

        $ownedInSession = in_array((int) $order['id'], $_SESSION['checkout_order_ids'] ?? [], true);
        $isOwner = isAuthenticated()
            && ($_SESSION['user_role'] ?? null) === 'customer'
            && $order['user_id'] !== null
            && (int) $order['user_id'] === (int) $_SESSION['user_id'];

        return $ownedInSession || $isOwner;
    }
}
