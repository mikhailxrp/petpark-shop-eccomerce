<?php

declare(strict_types=1);

/**
 * Карточка Товара в листинге каталога.
 * @var array<string, mixed> $product Строка productListByCategoryIds() —
 *      id, name, slug, category_name, price, discount_price,
 *      stock_quantity, reserved_quantity, image_path, variant_id
 *      (Вариант с каталожной ценой), variant_count (активных Вариантов)
 *
 * «В корзину»: один Вариант — POST-форма сразу с его variant_id;
 * несколько — ссылка на Карточку товара, где выбирается фасовка/вкус
 * (TASK.md Фазы 2, Таск 2, решение 2). Нет в наличии — кнопки нет.
 */

$hasDiscount = $product['discount_price'] !== null;
$effectivePrice = catalogEffectivePrice((float) $product['price'], $hasDiscount ? (float) $product['discount_price'] : null);
$status = catalogAvailabilityStatus((int) $product['stock_quantity'], (int) $product['reserved_quantity']);
$productUrl = '/product/' . $product['slug'] . '/';
// Заглушка до появления реальных фото товаров в /uploads — временно одна картинка на все карточки
$imageUrl = '/assets/img/food-1.png';
?>
<div class="col-md-4 col-sm-6">
    <div class="healthy-product">
        <div class="healthy-product-img">
            <img src="<?= e($imageUrl) ?>" alt="<?= e((string) $product['name']) ?>">
            <div class="add-to-cart">
                <?php if ((int) $product['variant_count'] > 1): ?>
                    <a href="<?= e($productUrl) ?>">Выбрать вариант</a>
                <?php elseif ($status !== 'out'): ?>
                    <form method="post" action="/cart/add" class="cart-add-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="variant_id" value="<?= (int) $product['variant_id'] ?>">
                        <input type="hidden" name="quantity" value="1">
                        <button type="submit" class="cart-add-form__button">В корзину</button>
                    </form>
                <?php endif; ?>
                <a href="#" class="heart-wishlist" aria-label="Добавить в избранное">
                    <i class="fa-regular fa-heart"></i>
                </a>
            </div>
        </div>
        <span><?= e((string) $product['category_name']) ?></span>
        <a href="<?= e($productUrl) ?>"><?= e((string) $product['name']) ?></a>
        <h6>
            <?php if ($hasDiscount): ?>
                <del><?= e(seoFormatPrice($product['price'])) ?> ₽</del>
                <ins><?= e(seoFormatPrice($effectivePrice)) ?> ₽</ins>
            <?php else: ?>
                <?= e(seoFormatPrice($effectivePrice)) ?> ₽
            <?php endif; ?>
        </h6>
        <p class="availability-label availability-label--<?= e($status) ?>">
            <?= e(catalogAvailabilityLabel($status)) ?>
        </p>
    </div>
</div>
