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
 * @var int    $total
 * @var string $sort
 * @var int    $page
 * @var int    $totalPages
 * @var string $actionPath Базовый URL без query — /catalog[/{cat}[/{sub}]] или /search (Таск 3)
 * @var array<string, mixed> $queryState Текущие фильтры/поиск
 *      (catalogFilterQueryParams(), Таск 3) — сохраняются при сортировке и пагинации
 */

$queryState ??= [];
?>
<div class="items-number">
    <span id="catalog-shown-count">Показано <?= count($products) ?> из <?= $total ?></span>
    <form method="get" action="<?= e($actionPath) ?>" class="d-flex align-items-center">
        <?php foreach (($queryState['attr'] ?? []) as $attrName => $values): ?>
            <?php foreach ($values as $value): ?>
                <input type="hidden" name="attr[<?= e($attrName) ?>][]" value="<?= e($value) ?>">
            <?php endforeach; ?>
        <?php endforeach; ?>
        <?php foreach (($queryState['brand'] ?? []) as $slug): ?>
            <input type="hidden" name="brand[]" value="<?= e($slug) ?>">
        <?php endforeach; ?>
        <?php if (($queryState['price_min'] ?? null) !== null): ?>
            <input type="hidden" name="price_min" value="<?= e((string) $queryState['price_min']) ?>">
        <?php endif; ?>
        <?php if (($queryState['price_max'] ?? null) !== null): ?>
            <input type="hidden" name="price_max" value="<?= e((string) $queryState['price_max']) ?>">
        <?php endif; ?>
        <?php if (($queryState['q'] ?? '') !== ''): ?>
            <input type="hidden" name="q" value="<?= e($queryState['q']) ?>">
        <?php endif; ?>
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
        <?php
        $hasActiveFilters = ($queryState['attr'] ?? []) !== []
            || ($queryState['brand'] ?? []) !== []
            || ($queryState['price_min'] ?? null) !== null
            || ($queryState['price_max'] ?? null) !== null;
        $searchQuery = $queryState['q'] ?? '';
        ?>
        <div class="col-12 empty-state">
            <?php if ($searchQuery !== '' && !$hasActiveFilters): ?>
                <p>По запросу «<?= e($searchQuery) ?>» ничего не найдено.</p>
            <?php elseif ($searchQuery !== ''): ?>
                <p>По запросу «<?= e($searchQuery) ?>» с выбранными фильтрами ничего не найдено.</p>
                <a href="<?= e($actionPath) ?>?q=<?= urlencode($searchQuery) ?>" class="button">Сбросить фильтр</a>
            <?php else: ?>
                <p>По этому фильтру товаров не найдено.</p>
                <a href="<?= e($actionPath) ?>" class="button">Сбросить фильтр</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php foreach ($products as $product): ?>
            <?php include __DIR__ . '/product-card.php'; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php
$basePath = $actionPath;
$extraQuery = $queryState;
include __DIR__ . '/pagination.php';
