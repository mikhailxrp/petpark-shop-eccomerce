<?php

declare(strict_types=1);

/**
 * «Заказ оформлен» — /checkout/success/{id} (phase-2.md, Таск 5,
 * FR-CHK-005). Доступ проверен в CheckoutController::canViewOrder() —
 * сюда попадают только сессия, оформившая Заказ, либо его владелец.
 *
 * @var array<string, mixed>          $order Models/Order.php orderFindById()
 * @var array<int, array<string, mixed>> $items orderItemsForOrder()
 */

$pageTitle = seoTitle('order-success');
$pageDescription = seoDescription('order-success');
$robotsNoindex = true;
$footerVariant = 'catalog';

$breadcrumbs = [
    ['name' => 'Главная', 'url' => '/'],
    ['name' => 'Корзина', 'url' => '/cart'],
    ['name' => 'Заказ оформлен', 'url' => null],
];

$deliveryMethodLabel = match ($order['delivery_method']) {
    'pickup'  => 'Самовывоз из магазина',
    'courier' => 'Курьером по ' . SHOP_CITY,
    default   => (string) $order['delivery_method'],
};

$paymentMethodLabel = match ($order['payment_method']) {
    'card_online'               => 'Картой на сайте',
    'cash_or_card_on_delivery'  => 'Наличными или картой при получении',
    default                     => (string) $order['payment_method'],
};

$paymentStatusLabel = match ($order['payment_status']) {
    'paid'     => 'Оплачен',
    'refunded' => 'Возвращён',
    default    => 'Ожидает оплаты',
};

$subtotal = orderKopecksToMoney(orderMoneyToKopecks((string) $order['total']) - orderMoneyToKopecks((string) $order['delivery_cost']));

ob_start();
?>
<section class="banner" style="background-color: #fff8e5; background-image: url(/assets/img/banners/banner-catalog.png)">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="banner-text">
                    <h1>Заказ оформлен</h1>
                    <?php include __DIR__ . '/components/breadcrumbs.php'; ?>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="banner-img">
                    <div class="banner-img-1">
                        <svg width="260" height="260" viewBox="0 0 673 673" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M9.82698 416.603C-19.0352 298.701 18.5108 173.372 107.497 90.7633L110.607 96.5197C24.3117 177.199 -12.311 298.935 15.0502 413.781L9.82698 416.603ZM89.893 565.433C172.674 654.828 298.511 692.463 416.766 663.224L414.077 658.245C298.613 686.363 175.954 649.666 94.9055 562.725L89.893 565.433ZM656.842 259.141C685.039 374.21 648.825 496.492 562.625 577.656L565.413 582.817C654.501 499.935 691.9 374.187 662.536 256.065L656.842 259.141ZM581.945 107.518C499.236 18.8371 373.997 -18.4724 256.228 10.5134L259.436 16.4515C373.888 -10.991 495.248 25.1518 576.04 110.708L581.945 107.518Z" fill="#fa441d"></path>
                        </svg>
                        <img src="/assets/img/banners/banner-img-1.jpg" alt="Французский бульдог ловит лакомство">
                    </div>
                    <div class="banner-img-2">
                        <svg width="320" height="320" viewBox="0 0 673 673" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M9.82698 416.603C-19.0352 298.701 18.5108 173.372 107.497 90.7633L110.607 96.5197C24.3117 177.199 -12.311 298.935 15.0502 413.781L9.82698 416.603ZM89.893 565.433C172.674 654.828 298.511 692.463 416.766 663.224L414.077 658.245C298.613 686.363 175.954 649.666 94.9055 562.725L89.893 565.433ZM656.842 259.141C685.039 374.21 648.825 496.492 562.625 577.656L565.413 582.817C654.501 499.935 691.9 374.187 662.536 256.065L656.842 259.141ZM581.945 107.518C499.236 18.8371 373.997 -18.4724 256.228 10.5134L259.436 16.4515C373.888 -10.991 495.248 25.1518 576.04 110.708L581.945 107.518Z" fill="#fa441d"></path>
                        </svg>
                        <img src="/assets/img/banners/banner-img-2.jpg" alt="Мужчина обнимает голден-ретривера">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <img src="/assets/img/hero-shaps-1.png" alt="" class="img-2">
    <img src="/assets/img/hero-shaps-1.png" alt="" class="img-4">
</section>
<section class="gap checkout-page order-success">
    <div class="container">
        <h2 class="order-success__title">Спасибо! Заказ №<?= (int) $order['id'] ?> оформлен</h2>
        <p>Мы свяжемся с вами по телефону <?= e((string) $order['contact_phone']) ?> для подтверждения.</p>

        <div class="row mt-4">
            <div class="col-12 col-lg-8">
                <h3>Состав заказа</h3>
                <table class="shop_table_responsive">
                    <thead>
                        <tr>
                            <th>Товар</th>
                            <th>Количество</th>
                            <th>Сумма</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php $lineTotal = orderKopecksToMoney(orderMoneyToKopecks((string) $item['price']) * (int) $item['quantity']); ?>
                            <tr>
                                <td>
                                    <?= e((string) $item['product_name']) ?>
                                    <div class="order-success__variant"><?= e((string) $item['variant_label']) ?></div>
                                </td>
                                <td><?= (int) $item['quantity'] ?></td>
                                <td><?= e(cartFormatMoney($lineTotal)) ?> ₽</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="col-12 col-lg-4 mt-4 mt-lg-0">
                <div class="cart_totals cart-Total">
                    <h4>Сумма заказа</h4>
                    <table class="shop_table_responsive">
                        <tbody>
                            <tr class="cart-subtotal">
                                <th>Товары:</th>
                                <td><?= e(cartFormatMoney($subtotal)) ?> ₽</td>
                            </tr>
                            <tr class="Shipping">
                                <th>Доставка:</th>
                                <td><?= e(cartFormatMoney((string) $order['delivery_cost'])) ?> ₽</td>
                            </tr>
                            <tr class="Total">
                                <th>Итого:</th>
                                <td><?= e(cartFormatMoney((string) $order['total'])) ?> ₽</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="checkout-side">
                    <h3>Доставка и оплата</h3>
                    <p><?= e($deliveryMethodLabel) ?></p>
                    <?php if ($order['delivery_address'] !== null): ?>
                        <p><?= e((string) $order['delivery_address']) ?></p>
                    <?php endif; ?>
                    <p><?= e($paymentMethodLabel) ?> — <?= e($paymentStatusLabel) ?></p>
                </div>
            </div>
        </div>
    </div>
</section>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/layouts/public.php';
