<?php

declare(strict_types=1);

/**
 * Список корневых Категорий для сайдбара каталога — один уровень, без
 * Подкатегорий (оригинальный макет, our-products.html: плоский список
 * "Cat Supplies / Dog Supplies / ..."). Клик по корневой Категории и так
 * открывает все её Подкатегории (родительская включает Подкатегории —
 * CatalogController), их отдельный показ в сайдбаре не нужен.
 * @var array<int, array<string, mixed>> $nodes    Корневые Категории (верхний уровень catalogBuildCategoryTree())
 * @var int|null                          $activeId id корневой Категории текущей ветки — подсвечивается
 * @var array<int, int>                   $counts   Категория id => число Товаров, с учётом Подкатегорий (catalogAggregateCategoryCounts())
 */

if ($nodes === []) {
    return;
}
?>
<ul class="category">
    <?php foreach ($nodes as $node): ?>
        <?php $isActive = $activeId !== null && (int) $node['id'] === $activeId; ?>
        <li<?= $isActive ? ' class="active"' : '' ?>>
            <a href="/catalog/<?= e((string) $node['slug']) ?>">
                <?= e((string) $node['name']) ?>
                <span><?= (int) ($counts[$node['id']] ?? 0) ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
