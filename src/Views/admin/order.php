<?php

declare(strict_types=1);

/**
 * Карточка Заказа — /admin/orders/{id} (phase-3.md, Таск 2; FR-ORD-001).
 * Только чтение: действия над Заказом — Таски 3–4.
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var array<string, mixed> $order orderFindForAdmin()
 * @var array<int, array<string, mixed>> $items orderItemsForOrder() — снэпшоты Позиций
 */

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
    <div class="card-header"><h2 class="card-title">Состав Заказа</h2></div>
    <div class="card-body">
        <div class="table-responsive position-relative">
            <table class="table text-nowrap align-middle">
                <thead>
                    <tr>
                        <th scope="col">Товар</th>
                        <th scope="col">Вариант</th>
                        <th scope="col">Цена, ₽</th>
                        <th scope="col">Кол-во</th>
                        <th scope="col">Сумма, ₽</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= e((string) $item['product_name']) ?></td>
                            <td><?= e((string) $item['variant_label']) ?></td>
                            <td><?= e(cartFormatMoney((string) $item['price'])) ?></td>
                            <td><?= (int) $item['quantity'] ?></td>
                            <td><?= e(cartFormatMoney(orderKopecksToMoney(orderMoneyToKopecks((string) $item['price']) * (int) $item['quantity']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row" colspan="4" class="text-end">Доставка</th>
                        <td><?= e(cartFormatMoney((string) $order['delivery_cost'])) ?></td>
                    </tr>
                    <tr>
                        <th scope="row" colspan="4" class="text-end">Итого</th>
                        <td><strong><?= e(cartFormatMoney((string) $order['total'])) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
