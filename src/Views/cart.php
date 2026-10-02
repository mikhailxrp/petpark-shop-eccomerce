<?php

declare(strict_types=1);

/**
 * Корзина — /cart (phase-2.md, Таск 2), макет SCR-04 `shop-cart.html`.
 * Поля купона нет (FR-CART-006), строки «Доставка» нет — она считается
 * на оформлении (FR-CART-003, правило 2). Кнопка «Оформить заказ» ведёт
 * на /checkout (phase-2.md, Таск 4). Без JS формы работают обычной
 * отправкой; public/assets/js/cart.js перехватывает их и перерисовывает
 * суммы из JSON сервера.
 * @var array<int, array<string, mixed>> $items    cartSummarize()['items'] — + line_total, available
 * @var string                           $subtotal cartSummarize()['subtotal'] — строка DECIMAL
 * @var string|null                      $notice   flash 'cart_notice'
 * @var string|null                      $error    flash 'cart_error'
 */

$pageTitle = seoTitle('cart');
$pageDescription = seoDescription('cart');
$robotsNoindex = true;
$footerVariant = 'catalog';

$breadcrumbs = [
    ['name' => 'Главная', 'url' => '/'],
    ['name' => 'Корзина', 'url' => null],
];

$message = $error ?? $notice;
$messageClass = $error !== null ? 'alert-danger' : 'alert-info';

ob_start();
?>
<section class="banner" style="background-color: #fff8e5; background-image: url(/assets/img/banners/banner-catalog.png)">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="banner-text">
                    <h1>Корзина</h1>
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
<section class="gap cart-page">
    <div class="container">
        <div
            id="cart-message"
            class="alert alert-dismissible <?= e($messageClass) ?><?= $message === null ? ' d-none' : '' ?>"
            role="status"
            aria-live="polite"
        >
            <span class="cart-message__text"><?= $message !== null ? e($message) : '' ?></span>
            <!-- Не data-bs-dismiss — плагин Bootstrap удаляет сам элемент из
                 DOM после закрытия, а cart.js переиспользует его для следующих
                 уведомлений (add/update/remove); закрытие — просто d-none. -->
            <button type="button" class="btn-close" id="cart-message-close" aria-label="Закрыть уведомление"></button>
        </div>

        <div id="cart-empty" class="cart-empty<?= $items !== [] ? ' d-none' : '' ?>">
            <h2 class="cart-empty__title">Корзина пуста</h2>
            <p class="cart-empty__text">Загляните в каталог — там корма, лакомства и всё для ухода за питомцем.</p>
            <a href="/catalog" class="button">Перейти в каталог</a>
        </div>

        <?php if ($items !== []): ?>
            <div id="cart-content">
                <h2 class="visually-hidden">Товары в корзине</h2>
                <div class="cart-table">
                    <table class="shop_table">
                        <thead>
                            <tr>
                                <th><span class="visually-hidden">Удалить</span></th>
                                <th class="product-name">Товар</th>
                                <th class="product-price">Цена</th>
                                <th class="product-quantity">Количество</th>
                                <th class="product-subtotal">Сумма</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <?php
                                $itemId = (int) $item['id'];
                                $hasDiscount = $item['discount_price'] !== null;
                                $effectivePrice = (string) ($hasDiscount ? $item['discount_price'] : $item['price']);
                                $itemUrl = '/product/' . $item['slug'] . '/?variant=' . (int) $item['variant_id'];
                                $isOverStock = (int) $item['quantity'] > (int) $item['available'];
                                ?>
                                <tr class="cart-item" data-item-id="<?= $itemId ?>">
                                    <td class="product-remove">
                                        <form method="post" action="/cart/remove" class="cart-item__remove-form">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="item_id" value="<?= $itemId ?>">
                                            <button type="submit" class="cart-item__remove" aria-label="Удалить «<?= e((string) $item['name']) ?>» из корзины">
                                                <i class="fa-solid fa-x" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="product-name">
                                        <img alt="<?= e((string) $item['name']) ?>" src="<?= e(catalogImageUrl($item['image_path'] ?? null)) ?>">
                                        <div>
                                            <span>Артикул: <?= e((string) $item['sku']) ?></span>
                                            <a href="<?= e($itemUrl) ?>"><?= e((string) $item['name']) ?></a>
                                        </div>
                                    </td>
                                    <td class="product-price">
                                        <span class="cart-item__price"><?= e(cartFormatMoney($effectivePrice)) ?> ₽</span>
                                        <?php if ($hasDiscount): ?>
                                            <del><?= e(cartFormatMoney((string) $item['price'])) ?> ₽</del>
                                        <?php endif; ?>
                                    </td>
                                    <td class="product-quantity">
                                        <form method="post" action="/cart/update" class="cart-item__quantity-form">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="item_id" value="<?= $itemId ?>">
                                            <label class="visually-hidden" for="cart-quantity-<?= $itemId ?>">Количество: <?= e((string) $item['name']) ?></label>
                                            <input
                                                type="number"
                                                class="input-text cart-item__quantity"
                                                id="cart-quantity-<?= $itemId ?>"
                                                name="quantity"
                                                min="0"
                                                max="<?= (int) $item['available'] ?>"
                                                step="1"
                                                value="<?= (int) $item['quantity'] ?>"
                                                required
                                            >
                                            <button type="submit" class="cart-item__update">Обновить</button>
                                        </form>
                                        <?php if ($isOverStock): ?>
                                            <p class="cart-item__warning">В наличии только <?= (int) $item['available'] ?> шт.</p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="product-subtotal">
                                        <span class="cart-item__line-total"><?= e(cartFormatMoney((string) $item['line_total'])) ?> ₽</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="row mt-5">
                    <div class="col-lg-7 offset-lg-5">
                        <div class="cart_totals">
                            <h3 class="cart-totals__title">Сумма заказа</h3>
                            <table class="shop_table_responsive">
                                <tbody>
                                    <tr class="Total">
                                        <th>Итого:</th>
                                        <td><span id="cart-subtotal"><?= e(cartFormatMoney($subtotal)) ?> ₽</span></td>
                                    </tr>
                                </tbody>
                            </table>
                            <p class="cart-totals__note">Стоимость доставки рассчитывается при оформлении заказа.</p>
                            <a href="/checkout" class="button cart-totals__checkout">Оформить заказ</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/layouts/public.php';
