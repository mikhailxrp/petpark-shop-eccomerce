<?php

declare(strict_types=1);

/**
 * Оформление заказа — /checkout (phase-2.md, Таск 4), макет SCR-05
 * `cart-checkout.html`. Демо-поля (Company/Country/City/State-Province)
 * и демо-способы оплаты (Bank/Check/PayPal) из макета убраны, узел
 * выбора доставки дорисован (FR-CHK-002, в макете отсутствовал).
 *
 * Обе стоимости доставки и итога уже посчитаны Controller'ом
 * (Core/Order.php) — их подставляет в data-атрибуты radio ниже,
 * public/assets/js/checkout.js только переключает готовые строки,
 * денег на клиенте не считает.
 *
 * Форма пока без обработчика — POST /checkout подключит Таск 5.
 *
 * @var string                        $subtotal     cartSummarize()['subtotal'] — строка DECIMAL
 * @var array{pickup: string, courier: string} $deliveryCost
 * @var array{pickup: string, courier: string} $total
 * @var array{name: string, phone: string, email: string} $contact
 * @var bool                          $isCustomer   авторизован как Покупатель
 */

$pageTitle = seoTitle('checkout');
$pageDescription = seoDescription('checkout');
$robotsNoindex = true;
$footerVariant = 'catalog';

$breadcrumbs = [
    ['name' => 'Главная', 'url' => '/'],
    ['name' => 'Корзина', 'url' => '/cart'],
    ['name' => 'Оформление заказа', 'url' => null],
];

ob_start();
?>
<section class="banner" style="background-color: #fff8e5; background-image: url(/assets/img/banners/banner-catalog.png)">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="banner-text">
                    <h1>Оформление заказа</h1>
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
<section class="gap checkout-page">
    <div class="container">
        <form class="checkout-meta donate-page" method="post" action="/checkout">
            <?= csrfField() ?>
            <div class="row">
                <div class="col-lg-8">
                    <h3>Контактные данные</h3>
                    <div class="col-lg-12">
                        <?php if (!$isCustomer): ?>
                            <p class="checkout-login-hint">
                                Уже покупали у нас? <a href="/login">Войдите</a> — данные подставятся автоматически.
                            </p>
                        <?php endif; ?>
                        <input type="text" class="input-text" name="contact_name" placeholder="Имя *" value="<?= e($contact['name']) ?>" required>
                        <input type="tel" class="input-text" name="contact_phone" placeholder="Телефон *" value="<?= e($contact['phone']) ?>" required>
                        <input type="email" class="input-text" name="contact_email" placeholder="Email *" value="<?= e($contact['email']) ?>" required>

                        <h3 class="checkout-section-title">Способ получения</h3>
                        <div class="ship-address checkout-delivery">
                            <div class="d-flex">
                                <input
                                    type="radio"
                                    id="delivery-pickup"
                                    name="delivery_method"
                                    value="pickup"
                                    data-cost="<?= e(cartFormatMoney($deliveryCost['pickup'])) ?> ₽"
                                    data-total="<?= e(cartFormatMoney($total['pickup'])) ?> ₽"
                                    checked
                                >
                                <label for="delivery-pickup">Самовывоз из магазина</label>
                            </div>
                            <div class="d-flex">
                                <input
                                    type="radio"
                                    id="delivery-courier"
                                    name="delivery_method"
                                    value="courier"
                                    data-cost="<?= e(cartFormatMoney($deliveryCost['courier'])) ?> ₽"
                                    data-total="<?= e(cartFormatMoney($total['courier'])) ?> ₽"
                                >
                                <label for="delivery-courier">Курьером по Ростову-на-Дону</label>
                            </div>
                        </div>

                        <div id="checkout-pickup-info" class="checkout-conditional">
                            <p><strong>Адрес магазина:</strong> <?= e(SHOP_PICKUP_ADDRESS) ?></p>
                            <p><strong>Часы работы:</strong> <?= e(SHOP_PICKUP_HOURS) ?></p>
                        </div>

                        <div id="checkout-address" class="checkout-conditional d-none">
                            <p class="checkout-address__city">Город доставки: Ростов-на-Дону</p>
                            <input type="text" class="input-text" name="delivery_street" placeholder="Улица *" required>
                            <div class="row">
                                <div class="col-lg-6">
                                    <input type="text" class="input-text" name="delivery_house" placeholder="Дом *" required>
                                </div>
                                <div class="col-lg-6">
                                    <input type="text" class="input-text" name="delivery_apartment" placeholder="Квартира/офис">
                                </div>
                            </div>
                            <div class="woocommerce-additional-fields">
                                <textarea name="delivery_comment" class="input-text" placeholder="Комментарий курьеру" maxlength="500"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="woocommerce-additional-fields">
                        <h3>Комментарий к заказу</h3>
                        <textarea name="customer_note" class="input-text" placeholder="Необязательно" maxlength="500"></textarea>
                    </div>
                </div>
            </div>
            <div class="row mt-lg-5">
                <div class="col-lg-6">
                    <div class="cart_totals cart-Total">
                        <h4>Сумма заказа</h4>
                        <table class="shop_table_responsive">
                            <tbody>
                                <tr class="cart-subtotal">
                                    <th>Товары:</th>
                                    <td><span id="checkout-subtotal"><?= e(cartFormatMoney($subtotal)) ?> ₽</span></td>
                                </tr>
                                <tr class="Shipping">
                                    <th>Доставка:</th>
                                    <td><span id="checkout-delivery-cost"><?= e(cartFormatMoney($deliveryCost['pickup'])) ?> ₽</span></td>
                                </tr>
                                <tr class="Total">
                                    <th>Итого:</th>
                                    <td><span id="checkout-total"><?= e(cartFormatMoney($total['pickup'])) ?> ₽</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="checkout-side">
                        <h3>Способ оплаты</h3>
                        <ul>
                            <li>
                                <input type="radio" id="payment-card" name="payment_method" value="card_online" required>
                                <label for="payment-card">Картой на сайте</label>
                            </li>
                            <li>
                                <input type="radio" id="payment-cash" name="payment_method" value="cash_or_card_on_delivery" required>
                                <label for="payment-cash">Наличными или картой при получении</label>
                            </li>
                        </ul>
                        <button type="submit" class="button">Оформить заказ</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/layouts/public.php';
