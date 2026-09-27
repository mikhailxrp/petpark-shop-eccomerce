<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Результаты поиска — /search?q=... (phase-1.md, Таск 3, FR-SRCH-002).
 * Отдельный экран, не Каталог: собственный маршрут, свой <title>
 * (Core/Seo.php, тип 'search'). Поиск по подстроке в названии —
 * productListByFilters()/productCountByFilters() (Models/Product.php),
 * те же условия фильтра, что и в CatalogController, плюс LIKE по имени.
 */
final class SearchController
{
    public function index(): void
    {
        $rawQuery = $_GET['q'] ?? null;
        $rawQueryTrimmed = is_string($rawQuery) ? trim($rawQuery) : '';
        $query = catalogNormalizeSearchQuery($rawQuery);
        $tooShort = $rawQueryTrimmed !== '' && $query === '';

        $attributeFacets = catalogFilterableAttributeFacets(productAttributeFacets());
        $brands = productActiveBrands();
        $attrFilter = catalogNormalizeAttrFilter($_GET['attr'] ?? null, $attributeFacets);
        $brandFilter = catalogNormalizeBrandFilter($_GET['brand'] ?? null, array_column($brands, 'slug'));
        [$priceMin, $priceMax] = $this->resolvePriceRange($_GET['price_min'] ?? null, $_GET['price_max'] ?? null);

        $sort = catalogNormalizeSort($_GET['sort'] ?? null);
        $page = catalogNormalizePage($_GET['page'] ?? null);

        $products = [];
        $total = 0;
        $totalPages = 1;

        if ($query !== '') {
            $total = productCountByFilters([], $attrFilter, $brandFilter, $priceMin, $priceMax, $query);
            $totalPages = max(1, (int) ceil($total / CATALOG_PER_PAGE));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * CATALOG_PER_PAGE;

            $products = $total > 0
                ? productListByFilters([], $attrFilter, $brandFilter, $priceMin, $priceMax, $query, $sort, CATALOG_PER_PAGE, $offset)
                : [];
        }

        $queryState = catalogFilterQueryParams([
            'attr'      => $attrFilter,
            'brand'     => $brandFilter,
            'price_min' => $priceMin,
            'price_max' => $priceMax,
            'q'         => $query,
        ]);

        if (isAjaxRequest()) {
            render('catalog-fragment', [
                'products'   => $products,
                'total'      => $total,
                'sort'       => $sort,
                'page'       => $page,
                'totalPages' => $totalPages,
                'actionPath' => '/search',
                'queryState' => $queryState,
            ]);
            return;
        }

        render('search', [
            'query'           => $rawQueryTrimmed,
            'tooShort'        => $tooShort,
            'attributeFacets' => $attributeFacets,
            'brands'          => $brands,
            'selectedAttrs'   => $attrFilter,
            'selectedBrands'  => $brandFilter,
            'priceMin'        => $priceMin,
            'priceMax'        => $priceMax,
            'priceBounds'     => productPriceRange(),
            'sort'            => $sort,
            'products'        => $products,
            'total'           => $total,
            'page'            => $page,
            'totalPages'      => $totalPages,
            'queryState'      => $queryState,
        ]);
    }

    /**
     * @return array{0: ?float, 1: ?float}
     */
    private function resolvePriceRange(mixed $rawMin, mixed $rawMax): array
    {
        $min = catalogNormalizePriceBound($rawMin);
        $max = catalogNormalizePriceBound($rawMax);

        if ($min !== null && $max !== null && $min > $max) {
            return [$max, $min];
        }

        return [$min, $max];
    }
}
