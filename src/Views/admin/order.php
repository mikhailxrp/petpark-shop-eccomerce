<?php

declare(strict_types=1);

/**
 * Карточка Заказа — /admin/orders/{id} (phase-3.md, Таск 2; FR-ORD-001).
 * Действия — блок «AmoCRM (демо)» и отметка оплаты (Таск 3); правка
 * состава — Таск 4.
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var array<string, mixed> $order orderFindForAdmin()
 * @var array<int, array<string, mixed>> $items orderItemsForOrder() — снэпшоты Позиций
 * @var array<int, string> $transitions допустимые статусы, в которые можно перейти
 * @var bool $canEditItems правка состава и цен доступна (new/confirmed/assembled)
 * @var string $freeThreshold порог бесплатной доставки курьером (BR-006)
 * @var string $courierCost стоимость курьерской доставки (BR-006)
 * @var string|null $success
 * @var string|null $error
 */

$transitionLabels = [
    'confirmed'        => 'Подтвердить',
    'assembled'        => 'Собран',
    'shipped'          => 'Передан курьеру',
    'ready_for_pickup' => 'Готов к выдаче',
    'delivered'        => 'Доставлен',
    'picked_up'        => 'Выдан',
    'cancelled'        => 'Отменить Заказ',
];
$canMarkPaid = $order['payment_method'] === 'cash_or_card_on_delivery'
    && $order['payment_status'] === 'unpaid'
    && $order['status'] !== 'cancelled';

$deliveryLabel = match ($order['delivery_method']) {
    'pickup'  => 'Самовывоз из магазина',
    'courier' => 'Курьером по ' . SHOP_CITY,
    default   => (string) $order['delivery_method'],
};
$paymentLabel = match ($order['payment_method']) {
    'card_online'              => 'Картой на сайте',
    'cash_or_card_on_delivery' => 'Наличными или картой при получении',
    default                    => (string) $order['payment_method'],
};
$paymentStatusLabel = match ($order['payment_status']) {
    'paid'     => 'Оплачен',
    'refunded' => 'Возвращён',
    default    => 'Ожидает оплаты',
};

ob_start();
?>
<?php if ($success !== null): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error !== null): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4">
    <div>
        <h1 class="mb-0">Заказ №<?= (int) $order['id'] ?></h1>
        <p class="mb-0 text-muted">
            От <?= e(date('d.m.Y H:i', (int) strtotime((string) $order['created_at']))) ?>
        </p>
    </div>
    <div class="mt-3 mt-md-0">
        <?php $badgeStatus = (string) $order['status']; ?>
        <?php include __DIR__ . '/../components/admin/order-status-badge.php'; ?>
        <a href="/admin/orders" class="btn btn-outline-secondary btn-sm ms-2">К списку</a>
    </div>
</div>

<div class="row">
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Покупатель</h2></div>
            <div class="card-body">
                <dl class="mb-0">
                    <dt>Имя</dt>
                    <dd><?= e((string) ($order['contact_name'] ?? '')) ?></dd>
                    <dt>Телефон</dt>
                    <dd><?= e((string) ($order['contact_phone'] ?? '')) ?></dd>
                    <dt>Email</dt>
                    <dd><?= e((string) ($order['contact_email'] ?? '')) ?></dd>
                    <dt>Комментарий Покупателя</dt>
                    <dd class="mb-0"><?= ($order['customer_note'] ?? '') !== '' ? e((string) $order['customer_note']) : '—' ?></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Доставка и оплата</h2></div>
            <div class="card-body">
                <dl class="mb-0">
                    <dt>Получение</dt>
                    <dd><?= e($deliveryLabel) ?></dd>
                    <?php if ($order['delivery_method'] === 'courier'): ?>
                        <dt>Адрес</dt>
                        <dd><?= e((string) ($order['delivery_address'] ?? '')) ?></dd>
                    <?php endif; ?>
                    <dt>Способ оплаты</dt>
                    <dd><?= e($paymentLabel) ?></dd>
                    <dt>Статус оплаты</dt>
                    <dd class="mb-0"><?= e($paymentStatusLabel) ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">AmoCRM (демо)</h2></div>
    <div class="card-body">
        <p class="text-muted">
            Сделка в AmoCRM:
            <?= ($order['amocrm_id'] ?? null) !== null ? e((string) $order['amocrm_id']) : 'ещё не создана (появится при подтверждении)' ?>
        </p>
        <?php if ($transitions === [] && !$canMarkPaid): ?>
            <p class="mb-0 text-muted">Для этого Заказа действий нет.</p>
        <?php endif; ?>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($transitions as $to): ?>
                <form method="post" action="/admin/orders/<?= (int) $order['id'] ?>/status">
                    <?= csrfField() ?>
                    <input type="hidden" name="status" value="<?= e($to) ?>">
                    <button type="submit" class="btn btn-sm <?= $to === 'cancelled' ? 'btn-outline-danger' : 'btn-primary' ?>">
                        <?= e($transitionLabels[$to] ?? $to) ?>
                    </button>
                </form>
            <?php endforeach; ?>
            <?php if ($canMarkPaid): ?>
                <form method="post" action="/admin/orders/<?= (int) $order['id'] ?>/mark-paid">
                    <?= csrfField() ?>
                    <button type="submit" class="btn btn-sm btn-success">Оплачено при получении</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Состав Заказа</h2></div>
    <div class="card-body">
        <div class="table-responsive position-relative">
            <table
                class="table text-nowrap align-middle"
                id="order-items-table"
                data-delivery-method="<?= e((string) $order['delivery_method']) ?>"
                data-free-threshold="<?= e($freeThreshold) ?>"
                data-courier-cost="<?= e($courierCost) ?>"
            >
                <thead>
                    <tr>
                        <th scope="col">Товар</th>
                        <th scope="col">Вариант</th>
                        <th scope="col">Цена, ₽</th>
                        <th scope="col">Кол-во</th>
                        <th scope="col">Сумма, ₽</th>
                        <?php if ($canEditItems): ?>
                            <th scope="col"><span class="visually-hidden">Действия</span></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php $itemId = (int) $item['id']; ?>
                        <tr>
                            <td><?= e((string) $item['product_name']) ?></td>
                            <td><?= e((string) $item['variant_label']) ?></td>
                            <?php if ($canEditItems): ?>
                                <td>
                                    <input
                                        type="text"
                                        inputmode="decimal"
                                        class="form-control form-control-sm"
                                        name="price"
                                        form="edit-item-<?= $itemId ?>"
                                        value="<?= e((string) $item['price']) ?>"
                                        aria-label="Цена, ₽: <?= e((string) $item['product_name']) ?>"
                                        data-item-price
                                        required
                                    >
                                </td>
                                <td>
                                    <input
                                        type="number"
                                        min="1"
                                        max="<?= ORDER_ITEM_MAX_QUANTITY ?>"
                                        class="form-control form-control-sm"
                                        name="quantity"
                                        form="edit-item-<?= $itemId ?>"
                                        value="<?= (int) $item['quantity'] ?>"
                                        aria-label="Количество: <?= e((string) $item['product_name']) ?>"
                                        data-item-quantity
                                        required
                                    >
                                </td>
                            <?php else: ?>
                                <td><?= e(cartFormatMoney((string) $item['price'])) ?></td>
                                <td><?= (int) $item['quantity'] ?></td>
                            <?php endif; ?>
                            <td><?= e(cartFormatMoney(orderKopecksToMoney(orderMoneyToKopecks((string) $item['price']) * (int) $item['quantity']))) ?></td>
                            <?php if ($canEditItems): ?>
                                <td>
                                    <form method="post" action="/admin/orders/<?= (int) $order['id'] ?>/items" id="edit-item-<?= $itemId ?>" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="item_id" value="<?= $itemId ?>">
                                        <button type="submit" name="action" value="update" class="btn btn-sm btn-primary">Сохранить</button>
                                        <button
                                            type="submit"
                                            name="action"
                                            value="remove"
                                            class="btn btn-sm btn-outline-danger"
                                            data-confirm="Удалить Позицию «<?= e((string) $item['product_name']) ?>» из Заказа?"
                                            formnovalidate
                                        >Удалить</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row" colspan="4" class="text-end">Доставка</th>
                        <td><?= e(cartFormatMoney((string) $order['delivery_cost'])) ?></td>
                        <?php if ($canEditItems): ?><td></td><?php endif; ?>
                    </tr>
                    <tr>
                        <th scope="row" colspan="4" class="text-end">Итого</th>
                        <td><strong><?= e(cartFormatMoney((string) $order['total'])) ?></strong></td>
                        <?php if ($canEditItems): ?><td></td><?php endif; ?>
                    </tr>
                    <?php if ($canEditItems): ?>
                        <tr>
                            <th scope="row" colspan="4" class="text-end">Итого после правки (предпросмотр)</th>
                            <td colspan="2"><span id="order-total-preview" aria-live="polite">—</span></td>
                        </tr>
                    <?php endif; ?>
                </tfoot>
            </table>
        </div>

        <?php if ($canEditItems): ?>
            <h3 class="h6 mt-4">Добавить Позицию</h3>
            <form method="post" action="/admin/orders/<?= (int) $order['id'] ?>/items" class="row g-2 align-items-end">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add">
                <div class="col-12 col-md-5">
                    <label for="add-item-sku" class="form-label">Артикул Варианта</label>
                    <input type="text" class="form-control" id="add-item-sku" name="sku" maxlength="64" required>
                </div>
                <div class="col-6 col-md-3">
                    <label for="add-item-quantity" class="form-label">Количество</label>
                    <input type="number" class="form-control" id="add-item-quantity" name="quantity" min="1" max="<?= ORDER_ITEM_MAX_QUANTITY ?>" value="1" required>
                </div>
                <div class="col-6 col-md-4">
                    <button type="submit" class="btn btn-primary w-100">Добавить</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
    <?php if ($canEditItems): ?>
        <script type="module" src="/assets/js/admin-order.js"></script>
    <?php endif; ?>
</div>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
