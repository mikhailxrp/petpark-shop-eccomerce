<?php

declare(strict_types=1);

/**
 * Результаты поиска — /search?q=... (phase-1.md, Таск 3, FR-SRCH-002).
 * Отдельный экран, не Каталог: своя компоновка (сайдбар фильтров +
 * components/catalog-results.php), собственный <title>/маршрут.
 * @var string $query    Исходный запрос из адресной строки (для заголовка)
 * @var bool   $tooShort Запрос введён, но короче 2 символов (FR-SRCH-001)
 * @var array<string, array<int, string>> $attributeFacets
 * @var array<int, array{slug: string, name: string}> $brands
 * @var array<string, array<int, string>> $selectedAttrs
 * @var array<int, string> $selectedBrands
 * @var float|null $priceMin
 * @var float|null $priceMax
 * @var array{min: float, max: float} $priceBounds
 * @var string $sort
 * @var array<int, array<string, mixed>> $products
 * @var int    $total
 * @var int    $page
 * @var int    $totalPages
 * @var array<string, mixed> $queryState
 */

$pageTitle = seoTitle('search', ['query' => $query]);
$pageDescription = seoDescription('search', ['query' => $query]);
$actionPath = '/search';
$robotsNoindex = true;
$footerVariant = 'catalog';

ob_start();
?>
<section class="gap products-section search-hero">
    <div class="container">
        <div class="row">
            <div class="col-lg-3">
                <?php include __DIR__ . '/components/catalog-filters.php'; ?>
            </div>
            <div class="col-lg-9" id="catalog-results">
                <h1>
                    Результаты поиска<?= $query !== '' ? ': «' . e($query) . '»' : '' ?>
                </h1>
                <?php if ($tooShort): ?>
                    <p class="search-hint">Запрос слишком короткий — введите минимум 2 символа.</p>
                <?php elseif ($query === ''): ?>
                    <p class="search-hint">Введите запрос в поле поиска, чтобы найти товар.</p>
                <?php else: ?>
                    <?php include __DIR__ . '/components/catalog-results.php'; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/layouts/public.php';
