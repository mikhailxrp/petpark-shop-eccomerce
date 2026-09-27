<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Карточка товара — /product/{slug}/ (phase-1.md, Таск 4). SQL — в
 * src/Models/Product.php; выбор Варианта по ?variant= — чистая функция
 * catalogSelectVariant() (Core/Catalog.php). Отзывы (FR-CARD-005) и
 * избранное (FR-CARD-006) — Таск 8, здесь не подключаются.
 */
final class ProductController
{
    // components/product-card.php — col-md-4 (3 карточки в ряд, md+),
    // 4 в этом ряду оставляли бы одну сиротой на отдельной строке.
    private const SIMILAR_PRODUCTS_LIMIT = 3;

    public function show(string $slug): void
    {
        $product = productFindBySlug($slug);
        if ($product === null) {
            http_response_code(404);
            render('errors/404');
            return;
        }

        $variants = productVariantsForProduct((int) $product['id']);
        if ($variants === []) {
            // Товар без активных Вариантов нечем показать — купить всё равно нельзя.
            http_response_code(404);
            render('errors/404');
            return;
        }

        $variantAttributes = productVariantAttributesForVariants(array_column($variants, 'id'));
        foreach ($variants as &$variant) {
            $variant['attributes'] = $variantAttributes[$variant['id']] ?? [];
        }
        unset($variant);

        $requestedVariantId = $_GET['variant'] ?? null;
        $selectedVariant = catalogSelectVariant($variants, is_string($requestedVariantId) ? $requestedVariantId : null);

        $categories = categoryAll();
        $categoryChain = catalogCategoryChain($categories, (int) $product['category_id']);

        // Похожие товары — в пределах корневой Категории (родитель
        // включает Подкатегории, тот же принцип, что у листинга каталога,
        // CatalogController::show()): при 1 Товаре на конечную Подкатегорию
        // (Таск 1, сид) поиск строго по своей же Подкатегории всегда
        // возвращал бы пусто.
        $rootCategoryId = (int) ($categoryChain[0]['id'] ?? $product['category_id']);
        $similarCategoryIds = catalogDescendantCategoryIds($categories, $rootCategoryId);

        render('product', [
            'product'         => $product,
            'variants'        => $variants,
            'selectedVariant' => $selectedVariant,
            'categoryChain'   => $categoryChain,
            'similarProducts' => productSimilarByCategory(
                $similarCategoryIds,
                (int) $product['id'],
                self::SIMILAR_PRODUCTS_LIMIT
            ),
        ]);
    }
}
