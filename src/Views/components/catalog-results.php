<?php

declare(strict_types=1);

/**
 * Результаты каталога — счётчик, сортировка, сетка Товаров, пагинация.
 * Общий фрагмент для полной страницы (catalog.php) и AJAX-ответа при
 * смене сортировки без перезагрузки (catalog-fragment.php,
 * public/assets/js/catalog.js) — один и тот же HTML в обоих случаях,
 * поэтому сортировка/пагинация ведут себя одинаково что при обычном
 * заходе, что при fetch().
 * @var array<int, array<string, mixed>> $products
 * @var array<int, array<string, mixed>> $categoryChain
 * @var int    $total
 * @var string $sort
 * @var int    $page
 * @var int    $totalPages
 */

$slugChain = array_column($categoryChain, 'slug');
$canonicalPath = catalogCanonicalPath($slugChain);
?>
<div class="items-number">
    <span id="catalog-shown-count">Показано <?= count($products) ?> из <?= $total ?></span>
    <form method="get" action="<?= e($canonicalPath) ?>" class="d-flex align-items-center">
        <label for="catalog-sort">Сортировка</label>
        <select name="sort" id="catalog-sort" class="nice-select Advice">
            <?php foreach (CATALOG_SORT_LABELS as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>>
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <noscript>
            <button type="submit" class="button">Применить</button>
        </noscript>
    </form>
</div>
<div class="row" id="catalog-products">
    <?php if ($products === []): ?>
        <div class="col-12 empty-state">
            <p>По этому фильтру товаров не найдено.</p>
            <a href="/catalog" class="button">Сбросить фильтр</a>
        </div>
    <?php else: ?>
        <?php foreach ($products as $product): ?>
            <?php include __DIR__ . '/product-card.php'; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php
$basePath = $canonicalPath;
include __DIR__ . '/pagination.php';
