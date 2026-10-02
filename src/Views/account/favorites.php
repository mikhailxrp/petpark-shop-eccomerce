<?php

declare(strict_types=1);

/**
 * Избранное — /account/favorites (`FR-ACC-004`, phase-7.md, Таск 4).
 * @var list<array<string, mixed>> $favorites favoritesByUser() + `status` (catalogAvailabilityStatus)
 * @var string|null                $success   getFlash('success')
 * @var string|null                $error     getFlash('error')
 */

$pageTitle = seoTitle('generic');
$pageDescription = seoDescription('generic');
$robotsNoindex = true;
$footerVariant = 'catalog';

$bannerTitle = 'Избранное';
$breadcrumbs = [
    ['name' => 'Главная', 'url' => '/'],
    ['name' => 'Личный кабинет', 'url' => '/account'],
    ['name' => 'Избранное', 'url' => null],
];

ob_start();
include __DIR__ . '/../components/page-banner.php';
?>
<section class="gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-3">
                <?php $accountActive = 'favorites'; include __DIR__ . '/../components/account-nav.php'; ?>
            </div>
            <div class="col-lg-9">
                <?php if ($success !== null): ?>
                    <div class="alert alert-success" role="status"><?= e($success) ?></div>
                <?php endif; ?>
                <?php if ($error !== null): ?>
                    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
                <?php endif; ?>

                <h2 class="account-heading">Избранные товары</h2>
                <?php if ($favorites === []): ?>
                    <p>В избранном пока пусто. <a href="/catalog">Перейти в каталог</a></p>
                <?php else: ?>
                    <?php foreach ($favorites as $favorite): ?>
                        <?php
                        $hasDiscount = $favorite['discount_price'] !== null;
                        $effectivePrice = catalogEffectivePrice(
                            (float) $favorite['price'],
                            $hasDiscount ? (float) $favorite['discount_price'] : null
                        );
                        $variantLabel = $favorite['attributes_label'] !== null
                            ? (string) $favorite['attributes_label']
                            : (string) $favorite['sku'];
                        $productUrl = '/product/' . $favorite['slug'] . '/?variant=' . (int) $favorite['variant_id'];
                        ?>
                        <article class="account-card favorite-card">
                            <h3 class="account-card__title">
                                <a href="<?= e($productUrl) ?>"><?= e((string) $favorite['name']) ?></a>
                            </h3>
                            <dl class="pet-card__details">
                                <dt>Вариант</dt>
                                <dd><?= e($variantLabel) ?></dd>
                                <dt>Цена</dt>
                                <dd>
                                    <?php if ($hasDiscount): ?>
                                        <del><?= e(seoFormatPrice($favorite['price'])) ?> ₽</del>
                                        <ins><?= e(seoFormatPrice($effectivePrice)) ?> ₽</ins>
                                    <?php else: ?>
                                        <?= e(seoFormatPrice($effectivePrice)) ?> ₽
                                    <?php endif; ?>
                                </dd>
                                <dt>Наличие</dt>
                                <dd>
                                    <span class="availability-label availability-label--<?= e((string) $favorite['status']) ?>">
                                        <?= e(catalogAvailabilityLabel((string) $favorite['status'])) ?>
                                    </span>
                                </dd>
                            </dl>
                            <div class="pet-card__actions">
                                <a class="button" href="<?= e($productUrl) ?>">Открыть товар</a>
                                <form method="post" action="/account/favorites/<?= (int) $favorite['variant_id'] ?>/remove">
                                    <?= csrfField() ?>
                                    <button type="submit" class="pet-card__delete">Убрать</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/public.php';
