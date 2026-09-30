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
    private const MARK_PAID_REJECTED_ERROR = 'Отметить оплату нельзя: способ оплаты другой, Заказ уже оплачен или отменён.';

    public function index(): void
    {
        requireRole('shift_admin', 'owner');

        $statusInput = input('status', '');
        $status = is_string($statusInput) && array_key_exists($statusInput, ORDER_STATUS_TRANSITIONS)
            ? $statusInput
            : null;

        $total = orderCountForAdmin($status);
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
            'orders'     => orderListForAdmin($status, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'status'     => $status,
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
        ]);
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
            'canEditItems' => orderIsEditable((string) $order['status']),
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

        $orderId = ctype_digit($id) ? (int) $id : 0;

        if ($orderId === 0 || orderFindById($orderId) === null) {
            http_response_code(404);
            render('errors/404');
            return;
        }

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
        $allowed = ORDER_STATUS_TRANSITIONS[(string) $order['status']] ?? [];

        return array_values(array_filter($allowed, static fn (string $to): bool => match ($to) {
            'shipped'          => $order['delivery_method'] === 'courier',
            'ready_for_pickup' => $order['delivery_method'] === 'pickup',
            default            => true,
        }));
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
