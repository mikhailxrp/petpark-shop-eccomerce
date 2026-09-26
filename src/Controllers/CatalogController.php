<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Листинг каталога — /catalog/, /catalog/{cat}/, /catalog/{cat}/{sub}/
 * (phase-1.md, Таск 2). SQL — в src/Models/Product.php и Category.php;
 * чистая логика (наличие, цена, сортировка, дерево/цепочка Категорий) —
 * в src/Core/Catalog.php. View сама строит <title>/breadcrumb-schema —
 * тот же паттерн, что src/Views/home.php.
 */
final class CatalogController
{
    public function index(): void
    {
        $this->show(null, null);
    }

    public function category(string $cat): void
    {
        $this->show($cat, null);
    }

    public function subcategory(string $cat, string $sub): void
    {
        $this->show($cat, $sub);
    }

    private function show(?string $catSlug, ?string $subSlug): void
    {
        $categories = categoryAll();
        $category = null;

        if ($catSlug !== null) {
            $category = $this->resolveCategory($categories, $catSlug, $subSlug);
            if ($category === null) {
                http_response_code(404);
                render('errors/404');
                return;
            }
        }

        $sort = catalogNormalizeSort($_GET['sort'] ?? null);
        $page = catalogNormalizePage($_GET['page'] ?? null);

        $categoryIds = $category !== null
            ? catalogDescendantCategoryIds($categories, (int) $category['id'])
            : array_map(static fn (array $row): int => (int) $row['id'], $categories);

        $total = productCountByCategoryIds($categoryIds);
        $totalPages = max(1, (int) ceil($total / CATALOG_PER_PAGE));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * CATALOG_PER_PAGE;

        $products = $total > 0
            ? productListByCategoryIds($categoryIds, $sort, CATALOG_PER_PAGE, $offset)
            : [];

        $categoryChain = $category !== null
            ? catalogCategoryChain($categories, (int) $category['id'])
            : [];

        // Сортировка/пагинация без перезагрузки (public/assets/js/catalog.js):
        // тот же запрос, тот же набор Товаров — меняется только шаблон ответа.
        if (isAjaxRequest()) {
            render('catalog-fragment', [
                'products'      => $products,
                'total'         => $total,
                'sort'          => $sort,
                'page'          => $page,
                'totalPages'    => $totalPages,
                'categoryChain' => $categoryChain,
            ]);
            return;
        }

        render('catalog', [
            'category'       => $category,
            'categoryChain'  => $categoryChain,
            'categoryTree'   => catalogBuildCategoryTree($categories),
            'categoryCounts' => catalogAggregateCategoryCounts($categories, productCountsByCategory()),
            'products'       => $products,
            'total'          => $total,
            'sort'           => $sort,
            'page'           => $page,
            'totalPages'     => $totalPages,
            'hasFilters'     => isset($_GET['sort']) || $page > 1,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $categories
     * @return array<string, mixed>|null
     */
    private function resolveCategory(array $categories, string $catSlug, ?string $subSlug): ?array
    {
        $bySlug = [];
        foreach ($categories as $row) {
            $bySlug[$row['slug']] = $row;
        }

        $cat = $bySlug[$catSlug] ?? null;
        if ($cat === null) {
            return null;
        }

        if ($subSlug === null) {
            return $cat;
        }

        $sub = $bySlug[$subSlug] ?? null;
        if ($sub === null || (int) $sub['parent_id'] !== (int) $cat['id']) {
            return null;
        }

        return $sub;
    }
}
