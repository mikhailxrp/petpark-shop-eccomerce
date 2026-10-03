<?php

declare(strict_types=1);

/**
 * Карточка Заказа — /account/orders/{id} (`FR-ACC-001`, phase-7.md, Таск 1).
 * @var array<string, mixed>       $order     orderFindById() — свой Заказ
 * @var list<array<string, mixed>> $items     orderItemsForOrder() — снэпшоты Позиций
 * @var bool                       $canReturn можно подать заявку на Возврат
 * @var bool                       $hasReturn по Заказу уже есть заявка на Возврат
 * @var bool                       $canCancel Покупатель может отменить Заказ (ADR-032)
 * @var string|null                $success   getFlash('success')
 * @var string|null                $error     getFlash('error')
 */

$pageTitle = seoTitle('account-order', $order);
$pageDescription = seoDescription('account-order', $order);
$robotsNoindex = true;
$footerVariant = 'catalog';

$bannerTitle = 'Заказ №' . (int) $order['id'];
$breadcrumbs = [
    ['name' => 'Главная', 'url' => '/'],
    ['name' => 'Личный кабинет', 'url' => '/account'],
    ['name' => 'Мои заказы', 'url' => '/account/orders'],
    ['name' => 'Заказ №' . (int) $order['id'], 'url' => null],
];

$orderStatusLabels = [
    'new'              => 'Новый',
    'confirmed'        => 'Подтверждён',
    'assembled'        => 'Собран',
    'shipped'          => 'Отгружен',
    'ready_for_pickup' => 'Готов к выдаче',
    'delivered'        => 'Доставлен',
    'picked_up'        => 'Выдан',
    'cancelled'        => 'Отменён',
];
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
$orderedAt = new DateTimeImmutable((string) $order['created_at']);
$hasDeliveryCost = orderMoneyToKopecks((string) $order['delivery_cost']) > 0;

ob_start();
include __DIR__ . '/../components/page-banner.php';
?>
<section class="gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-3">
                <?php $accountActive = 'orders'; include __DIR__ . '/../components/account-nav.php'; ?>
            </div>
            <div class="col-lg-9">
                <?php if ($success !== null): ?>
                    <div class="alert alert-success" role="status"><?= e($success) ?></div>
                <?php endif; ?>
                <?php if ($error !== null): ?>
                    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
                <?php endif; ?>

                <h2 class="account-heading">
                    Заказ от <?= e($orderedAt->format('d.m.Y')) ?>
                    <span class="order-status order-status--<?= e((string) $order['status']) ?>">
                        <?= e($orderStatusLabels[$order['status']] ?? (string) $order['status']) ?>
                    </span>
                </h2>

                <article class="account-card">
                    <h3 class="account-card__title">Состав заказа</h3>
                    <ul class="order-items">
                        <?php foreach ($items as $item): ?>
                            <?php $lineKopecks = orderMoneyToKopecks((string) $item['price']) * (int) $item['quantity']; ?>
                            <li class="order-items__row">
                                <span class="order-items__name">
                                    <?= e((string) $item['product_name']) ?><?php if ((string) $item['variant_label'] !== ''): ?>, <?= e((string) $item['variant_label']) ?><?php endif; ?>
                                </span>
                                <span class="order-items__qty">
                                    <?= e(cartFormatMoney((string) $item['price'])) ?> ₽ × <?= (int) $item['quantity'] ?>
                                </span>
                                <span class="order-items__sum"><?= e(cartFormatMoney(orderKopecksToMoney($lineKopecks))) ?> ₽</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <dl class="pet-card__details">
                        <dt>Доставка</dt>
                        <dd><?= $hasDeliveryCost ? e(cartFormatMoney((string) $order['delivery_cost'])) . ' ₽' : 'Бесплатно' ?></dd>
                        <dt>Итого</dt>
                        <dd><strong><?= e(cartFormatMoney((string) $order['total'])) ?> ₽</strong></dd>
                    </dl>
                </article>

                <article class="account-card">
                    <h3 class="account-card__title">Получение и оплата</h3>
                    <dl class="pet-card__details">
                        <dt>Получение</dt>
                        <dd><?= e($deliveryLabel) ?></dd>
                        <?php if ((string) ($order['delivery_address'] ?? '') !== ''): ?>
                            <dt>Адрес</dt>
                            <dd><?= e((string) $order['delivery_address']) ?></dd>
                        <?php endif; ?>
                        <dt>Оплата</dt>
                        <dd><?= e($paymentLabel) ?></dd>
                        <dt>Статус оплаты</dt>
                        <dd><?= e($paymentStatusLabel) ?></dd>
                        <?php if ((string) ($order['customer_note'] ?? '') !== ''): ?>
                            <dt>Примечание</dt>
                            <dd><?= e((string) $order['customer_note']) ?></dd>
                        <?php endif; ?>
                    </dl>
                </article>

                <div class="pet-card__actions">
                    <?php if ($canReturn): ?>
                        <a class="button" href="/account/returns/<?= (int) $order['id'] ?>/new">Оформить возврат</a>
                    <?php elseif ($hasReturn): ?>
                        <a class="button" href="/account/returns">Заявка на возврат</a>
                    <?php endif; ?>
                    <?php if ($canCancel): ?>
                        <button type="button" class="button" data-bs-toggle="modal" data-bs-target="#order-cancel-modal">
                            Отменить заказ
                        </button>
                    <?php endif; ?>
                    <a href="/account/orders">← Все заказы</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($canCancel): ?>
    <div class="modal fade" id="order-cancel-modal" tabindex="-1" aria-labelledby="order-cancel-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="post" action="/account/orders/<?= (int) $order['id'] ?>/cancel">
                <?= csrfField() ?>
                <div class="modal-header">
                    <h2 class="modal-title order-cancel-modal__title" id="order-cancel-title">Отменить заказ №<?= (int) $order['id'] ?>?</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <p>
                        Заказ будет отменён, товары вернутся в наличие.
                        <?php if ($order['payment_status'] === 'paid' && $order['payment_method'] === 'card_online'): ?>
                            Деньги вернутся на карту, которой вы оплатили заказ.
                        <?php endif; ?>
                        Действие нельзя отменить.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="button" data-bs-dismiss="modal">Не отменять</button>
                    <button type="submit" class="button">Отменить заказ</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/public.php';
