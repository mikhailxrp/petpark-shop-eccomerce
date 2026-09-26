<?php

declare(strict_types=1);

/**
 * Модель Товаров для листинга каталога — только SQL через PDO, возвращает
 * массивы (php.md). Только активные Товары с хотя бы одним активным
 * Вариантом; каталожная цена/наличие — по Варианту с минимальной ценой
 * (database.md: «каталожная цена — минимальная среди активных Вариантов»).
 */

function productCountByCategoryIds(array $categoryIds): int
{
    if ($categoryIds === []) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
    $stmt = getPdo()->prepare("
        SELECT COUNT(*)
        FROM products p
        WHERE p.is_active = 1
          AND p.category_id IN ({$placeholders})
          AND EXISTS (
              SELECT 1 FROM product_variants v
              WHERE v.product_id = p.id AND v.is_active = 1
          )
    ");
    $stmt->execute(array_values($categoryIds));

    return (int) $stmt->fetchColumn();
}

/**
 * Число активных Товаров на Категорию (без учёта Подкатегорий — их
 * добавляет catalogAggregateCategoryCounts(), Core/Catalog.php) — для
 * счётчика в сайдбаре, один запрос на все Категории сразу.
 *
 * @return array<int, int> Категория id => число Товаров
 */
function productCountsByCategory(): array
{
    $stmt = getPdo()->query('
        SELECT p.category_id, COUNT(*) AS product_count
        FROM products p
        WHERE p.is_active = 1
          AND EXISTS (
              SELECT 1 FROM product_variants v
              WHERE v.product_id = p.id AND v.is_active = 1
          )
        GROUP BY p.category_id
    ');

    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        $counts[(int) $row['category_id']] = (int) $row['product_count'];
    }

    return $counts;
}

/**
 * @param array<int, int> $categoryIds
 */
function productListByCategoryIds(array $categoryIds, string $sort, int $limit, int $offset): array
{
    if ($categoryIds === []) {
        return [];
    }

    $orderBy = match ($sort) {
        'price_asc' => 'effective_price ASC, p.id ASC',
        'price_desc' => 'effective_price DESC, p.id ASC',
        'new' => 'p.created_at DESC, p.id DESC',
        // popularity (по умолчанию, FR-CAT-004): ручной отбор Владельца
        // (popularity_rank), остальные товары — следом, по id
        default => 'p.popularity_rank IS NULL ASC, p.popularity_rank ASC, p.id ASC',
    };

    // $limit/$offset — всегда серверные целые (CATALOG_PER_PAGE, номер
    // страницы после catalogNormalizePage), не сырой ввод пользователя;
    // подставляются как (int), а не через bindValue — PDO с отключенной
    // эмуляцией не принимает строковые параметры в LIMIT/OFFSET.
    $limit = (int) $limit;
    $offset = (int) $offset;

    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
    $stmt = getPdo()->prepare("
        SELECT
            p.id, p.name, p.slug, p.seo_title, p.seo_description,
            c.name AS category_name,
            v.price, v.discount_price, v.stock_quantity, v.reserved_quantity,
            IFNULL(v.discount_price, v.price) AS effective_price,
            img.path AS image_path
        FROM products p
        JOIN categories c ON c.id = p.category_id
        JOIN (
            SELECT product_id, price, discount_price, stock_quantity, reserved_quantity,
                   ROW_NUMBER() OVER (
                       PARTITION BY product_id
                       ORDER BY IFNULL(discount_price, price) ASC, id ASC
                   ) AS rn
            FROM product_variants
            WHERE is_active = 1
        ) v ON v.product_id = p.id AND v.rn = 1
        LEFT JOIN (
            SELECT product_id, path,
                   ROW_NUMBER() OVER (
                       PARTITION BY product_id
                       ORDER BY is_main DESC, sort_order ASC, id ASC
                   ) AS rn
            FROM product_images
        ) img ON img.product_id = p.id AND img.rn = 1
        WHERE p.is_active = 1
          AND p.category_id IN ({$placeholders})
        ORDER BY {$orderBy}
        LIMIT {$limit} OFFSET {$offset}
    ");
    $stmt->execute(array_values($categoryIds));

    return $stmt->fetchAll();
}
