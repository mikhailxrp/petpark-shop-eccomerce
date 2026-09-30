<?php

declare(strict_types=1);

/**
 * Модель Товаров для листинга каталога — только SQL через PDO, возвращает
 * массивы (php.md). Только активные Товары с хотя бы одним активным
 * Вариантом; каталожная цена/наличие — по Варианту с минимальной ценой
 * (database.md: «каталожная цена — минимальная среди активных Вариантов»).
 */

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
 * Собирает общее WHERE-условие фильтра каталога/поиска — Категория,
 * Характеристики (ИЛИ внутри одной, И между разными — FR-CAT-002),
 * Бренд, диапазон цены, подстрока в названии (поиск, FR-SRCH-002).
 * Единственное место со сборкой условий — используется и в
 * productCountByFilters(), и в productListByFilters(), не дублируется.
 * Диапазон цены сравнивается с `v.effective_price` — тем же Вариантом
 * с минимальной ценой, что показан на карточке (JOIN v в обеих
 * функциях), а не отдельным EXISTS, иначе возможно расхождение с
 * отображаемой ценой (dod-global.md, SEO).
 *
 * @param array<int, int> $categoryIds
 * @param array<string, array<int, string>> $attrFilter attr_name => значения (ИЛИ)
 * @param array<int, string> $brandSlugs
 * @return array{where: string, params: list<mixed>}
 */
function productFilterConditions(
    array $categoryIds,
    array $attrFilter,
    array $brandSlugs,
    ?float $priceMin,
    ?float $priceMax,
    ?string $search
): array {
    $conditions = ['p.is_active = 1'];
    $params = [];

    if ($categoryIds !== []) {
        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $conditions[] = "p.category_id IN ({$placeholders})";
        array_push($params, ...array_values($categoryIds));
    }

    foreach ($attrFilter as $attrName => $values) {
        if ($values === []) {
            continue;
        }
        $valuePlaceholders = implode(',', array_fill(0, count($values), '?'));
        // Характеристика может быть на уровне Товара (product_attributes,
        // напр. вид_животного) или Варианта (product_variant_attributes,
        // напр. вес упаковки/цвет, ADR-005) — проверяем оба источника.
        $conditions[] = "(
            EXISTS (
                SELECT 1 FROM product_attributes pa
                WHERE pa.product_id = p.id AND pa.attr_name = ? AND pa.attr_value IN ({$valuePlaceholders})
            )
            OR EXISTS (
                SELECT 1 FROM product_variant_attributes pva
                JOIN product_variants pav ON pav.id = pva.variant_id
                WHERE pav.product_id = p.id AND pva.attr_name = ? AND pva.attr_value IN ({$valuePlaceholders})
            )
        )";
        $params[] = $attrName;
        array_push($params, ...array_values($values));
        $params[] = $attrName;
        array_push($params, ...array_values($values));
    }

    if ($brandSlugs !== []) {
        $placeholders = implode(',', array_fill(0, count($brandSlugs), '?'));
        $conditions[] = "p.brand_id IN (SELECT id FROM brands WHERE slug IN ({$placeholders}))";
        array_push($params, ...array_values($brandSlugs));
    }

    if ($search !== null && $search !== '') {
        $conditions[] = 'p.name LIKE ?';
        $params[] = '%' . $search . '%';
    }

    if ($priceMin !== null) {
        $conditions[] = 'v.effective_price >= ?';
        $params[] = $priceMin;
    }
    if ($priceMax !== null) {
        $conditions[] = 'v.effective_price <= ?';
        $params[] = $priceMax;
    }

    return ['where' => implode(' AND ', $conditions), 'params' => $params];
}

/**
 * @param array<int, int> $categoryIds
 * @param array<string, array<int, string>> $attrFilter
 * @param array<int, string> $brandSlugs
 */
function productCountByFilters(
    array $categoryIds,
    array $attrFilter = [],
    array $brandSlugs = [],
    ?float $priceMin = null,
    ?float $priceMax = null,
    ?string $search = null
): int {
    ['where' => $where, 'params' => $params] = productFilterConditions(
        $categoryIds,
        $attrFilter,
        $brandSlugs,
        $priceMin,
        $priceMax,
        $search
    );

    $stmt = getPdo()->prepare("
        SELECT COUNT(*)
        FROM products p
        JOIN (
            SELECT product_id, IFNULL(discount_price, price) AS effective_price,
                   ROW_NUMBER() OVER (
                       PARTITION BY product_id
                       ORDER BY IFNULL(discount_price, price) ASC, id ASC
                   ) AS rn
            FROM product_variants
            WHERE is_active = 1
        ) v ON v.product_id = p.id AND v.rn = 1
        WHERE {$where}
    ");
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

/**
 * @param array<int, int> $categoryIds
 * @param array<string, array<int, string>> $attrFilter
 * @param array<int, string> $brandSlugs
 */
function productListByFilters(
    array $categoryIds,
    array $attrFilter,
    array $brandSlugs,
    ?float $priceMin,
    ?float $priceMax,
    ?string $search,
    string $sort,
    int $limit,
    int $offset
): array {
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

    ['where' => $where, 'params' => $params] = productFilterConditions(
        $categoryIds,
        $attrFilter,
        $brandSlugs,
        $priceMin,
        $priceMax,
        $search
    );

    $stmt = getPdo()->prepare("
        SELECT
            p.id, p.name, p.slug, p.seo_title, p.seo_description,
            c.name AS category_name,
            v.variant_id, v.variant_count,
            v.price, v.discount_price, v.stock_quantity, v.reserved_quantity,
            v.effective_price,
            img.path AS image_path
        FROM products p
        JOIN categories c ON c.id = p.category_id
        JOIN (
            SELECT product_id, id AS variant_id, price, discount_price, stock_quantity, reserved_quantity,
                   COUNT(*) OVER (PARTITION BY product_id) AS variant_count,
                   IFNULL(discount_price, price) AS effective_price,
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
        WHERE {$where}
        ORDER BY {$orderBy}
        LIMIT {$limit} OFFSET {$offset}
    ");
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/**
 * Доступные Характеристики для сайдбара фильтра (FR-CAT-002) — из обоих
 * источников: product_attributes (уровень Товара, напр. вид_животного)
 * и product_variant_attributes (уровень Варианта, напр. вес упаковки/
 * цвет, ADR-005) активных Товаров/Вариантов. Список общий по всему
 * каталогу, не сужается текущей Категорией — простое и предсказуемое
 * поведение для первой версии фильтра.
 *
 * @return array<string, array<int, string>> attr_name => доступные значения
 */
function productAttributeFacets(): array
{
    $pdo = getPdo();

    $rows = [
        ...$pdo->query('
            SELECT DISTINCT pa.attr_name, pa.attr_value
            FROM product_attributes pa
            JOIN products p ON p.id = pa.product_id
            WHERE p.is_active = 1
            ORDER BY pa.attr_name, pa.attr_value
        ')->fetchAll(),
        ...$pdo->query('
            SELECT DISTINCT pva.attr_name, pva.attr_value
            FROM product_variant_attributes pva
            JOIN product_variants v ON v.id = pva.variant_id
            JOIN products p ON p.id = v.product_id
            WHERE p.is_active = 1 AND v.is_active = 1
            ORDER BY pva.attr_name, pva.attr_value
        ')->fetchAll(),
    ];

    $facets = [];
    foreach ($rows as $row) {
        $facets[$row['attr_name']][] = $row['attr_value'];
    }

    foreach ($facets as $attrName => $values) {
        $facets[$attrName] = array_values(array_unique($values));
    }

    return $facets;
}

/**
 * Бренды, у которых есть хотя бы один активный Товар — для сайдбара
 * фильтра (пустые варианты фильтра не показываются).
 *
 * @return array<int, array{slug: string, name: string}>
 */
function productActiveBrands(): array
{
    return getPdo()->query('
        SELECT DISTINCT b.slug, b.name
        FROM brands b
        JOIN products p ON p.brand_id = b.id
        WHERE p.is_active = 1
        ORDER BY b.name
    ')->fetchAll();
}

/**
 * Товар по slug для Карточки (phase-1.md, Таск 4) — только активный,
 * иначе Controller отдаёт 404 (dod-global.md).
 *
 * @return array<string, mixed>|null
 */
function productFindBySlug(string $slug): ?array
{
    $stmt = getPdo()->prepare('
        SELECT id, category_id, name, slug, description, seo_title, seo_description
        FROM products
        WHERE slug = ? AND is_active = 1
    ');
    $stmt->execute([$slug]);
    $product = $stmt->fetch();

    return $product !== false ? $product : null;
}

/**
 * Активные Варианты Товара для переключателя на Карточке (FR-CARD-001) —
 * упорядочены по эффективной цене, чтобы совпадать с дефолтом
 * catalogSelectVariant() (Core/Catalog.php).
 *
 * @return array<int, array<string, mixed>>
 */
function productVariantsForProduct(int $productId): array
{
    $stmt = getPdo()->prepare('
        SELECT id, sku, price, discount_price, stock_quantity, reserved_quantity
        FROM product_variants
        WHERE product_id = ? AND is_active = 1
        ORDER BY IFNULL(discount_price, price) ASC, id ASC
    ');
    $stmt->execute([$productId]);

    return $stmt->fetchAll();
}

/**
 * Характеристики Вариантов (вес/вкус и т.п., ADR-005) для набора
 * Вариантов — группируются по variant_id, для сборки подписи
 * переключателя («2 кг, курица», тот же формат, что
 * `order_items.variant_label`, database.md).
 *
 * @param array<int, int> $variantIds
 * @return array<int, array<int, string>> variant_id => список attr_value по порядку attr_name
 */
function productVariantAttributesForVariants(array $variantIds): array
{
    if ($variantIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
    $stmt = getPdo()->prepare("
        SELECT variant_id, attr_name, attr_value
        FROM product_variant_attributes
        WHERE variant_id IN ({$placeholders})
        ORDER BY variant_id, attr_name
    ");
    $stmt->execute(array_values($variantIds));

    $attributes = [];
    foreach ($stmt->fetchAll() as $row) {
        $attributes[(int) $row['variant_id']][] = $row['attr_value'];
    }

    return $attributes;
}

/**
 * Похожие товары (FR-CARD-004) — $categoryIds передаются Controller'ом
 * как поддерево корневой Категории (catalogDescendantCategoryIds() от
 * корня цепочки, тот же принцип «родитель включает Подкатегории», что
 * у листинга каталога): в текущих данных (Таск 1) у каждой конечной
 * Подкатегории ровно 1 Товар, поиск похожих строго по своей же
 * Подкатегории всегда возвращал бы пусто. Форма строки — та же, что
 * productListByFilters(), чтобы переиспользовать
 * components/product-card.php без изменений.
 *
 * @param array<int, int> $categoryIds
 * @return array<int, array<string, mixed>>
 */
function productSimilarByCategory(array $categoryIds, int $excludeProductId, int $limit): array
{
    if ($categoryIds === []) {
        return [];
    }

    $limit = (int) $limit;
    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

    $stmt = getPdo()->prepare("
        SELECT
            p.id, p.name, p.slug,
            c.name AS category_name,
            v.variant_id, v.variant_count,
            v.price, v.discount_price, v.stock_quantity, v.reserved_quantity,
            img.path AS image_path
        FROM products p
        JOIN categories c ON c.id = p.category_id
        JOIN (
            SELECT product_id, id AS variant_id, price, discount_price, stock_quantity, reserved_quantity,
                   COUNT(*) OVER (PARTITION BY product_id) AS variant_count,
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
        WHERE p.is_active = 1 AND p.category_id IN ({$placeholders}) AND p.id != ?
        ORDER BY p.popularity_rank IS NULL, p.popularity_rank ASC, p.id ASC
        LIMIT {$limit}
    ");
    $stmt->execute([...array_values($categoryIds), $excludeProductId]);

    return $stmt->fetchAll();
}

/**
 * Границы цены по каталогу (минимальная/максимальная эффективная цена
 * среди активных Вариантов активных Товаров) — задают min/max
 * range-слайдера фильтра цены (FR-CAT-003).
 *
 * @return array{min: float, max: float}
 */
function productPriceRange(): array
{
    $row = getPdo()->query('
        SELECT
            MIN(IFNULL(v.discount_price, v.price)) AS min_price,
            MAX(IFNULL(v.discount_price, v.price)) AS max_price
        FROM product_variants v
        JOIN products p ON p.id = v.product_id
        WHERE v.is_active = 1 AND p.is_active = 1
    ')->fetch();

    return [
        'min' => $row['min_price'] !== null ? (float) $row['min_price'] : 0.0,
        'max' => $row['max_price'] !== null ? (float) $row['max_price'] : 0.0,
    ];
}

/**
 * Активные Товары для sitemap.xml (phase-1.md, Таск 9, [INFRA]) — только
 * slug и updated_at (для <lastmod>), без Вариантов/фото/связей: sitemap
 * не показывает карточку, ему не нужна каталожная цена.
 *
 * @return array<int, array{slug: string, updated_at: string}>
 */
function productActiveForSitemap(): array
{
    return getPdo()->query('
        SELECT slug, updated_at
        FROM products
        WHERE is_active = 1
    ')->fetchAll();
}

/**
 * «Хиты продаж» для Главной (FR-HOME-005) — отбор вручную Владельцем
 * (`is_featured`, `database.md` ADR), не алгоритм. Та же форма строки и
 * тот же приём минимальной цены среди активных Вариантов
 * (ROW_NUMBER()), что у productSimilarByCategory() — совместимо с
 * components/product-card.php без изменений.
 */
function productFeatured(int $limit): array
{
    $limit = (int) $limit;

    $stmt = getPdo()->query("
        SELECT
            p.id, p.name, p.slug,
            c.name AS category_name,
            v.variant_id, v.variant_count,
            v.price, v.discount_price, v.stock_quantity, v.reserved_quantity,
            img.path AS image_path
        FROM products p
        JOIN categories c ON c.id = p.category_id
        JOIN (
            SELECT product_id, id AS variant_id, price, discount_price, stock_quantity, reserved_quantity,
                   COUNT(*) OVER (PARTITION BY product_id) AS variant_count,
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
        WHERE p.is_active = 1 AND p.is_featured = 1
        ORDER BY p.popularity_rank IS NULL, p.popularity_rank ASC, p.id ASC
        LIMIT {$limit}
    ");

    return $stmt->fetchAll();
}

/**
 * Поиск активных Вариантов для ручного Заказа (FR-ORD-003): артикул — по
 * префиксу, название Товара — по вхождению. Остаток и цена — живые, из БД.
 *
 * @return array<int, array<string, mixed>>
 */
function productSearchVariants(string $query, int $limit): array
{
    $escaped = addcslashes($query, '\%_');

    $stmt = getPdo()->prepare('
        SELECT v.id AS variant_id, v.sku, p.name, v.price, v.discount_price,
               v.stock_quantity, v.reserved_quantity,
               (
                   SELECT GROUP_CONCAT(a.attr_value ORDER BY a.attr_name SEPARATOR \', \')
                   FROM product_variant_attributes a
                   WHERE a.variant_id = v.id
               ) AS attributes_label
        FROM product_variants v
        JOIN products p ON p.id = v.product_id AND p.is_active = 1
        WHERE v.is_active = 1 AND (v.sku LIKE :sku_prefix OR p.name LIKE :name_part)
        ORDER BY p.name ASC, v.id ASC
        LIMIT :row_limit
    ');
    $stmt->bindValue('sku_prefix', $escaped . '%');
    $stmt->bindValue('name_part', '%' . $escaped . '%');
    $stmt->bindValue('row_limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    // Подпись Варианта — одним запросом: на удалённой БД каждый лишний
    // round-trip заметен в живом поиске.
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['variant_label'] = $row['attributes_label'] !== null ? (string) $row['attributes_label'] : (string) $row['sku'];
        unset($row['attributes_label']);
    }
    unset($row);

    return $rows;
}

/**
 * Активные Варианты по id (с названием Товара), ключ — variant_id; той же
 * формы, что cartItemsForOwner(), чтобы ядро создания Заказа работало с
 * любым источником позиций. Неактивные и несуществующие в результат не
 * попадают.
 *
 * @param array<int, int> $variantIds
 * @return array<int, array<string, mixed>>
 */
function productActiveVariantsByIds(array $variantIds): array
{
    if ($variantIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
    $stmt = getPdo()->prepare("
        SELECT v.id AS variant_id, v.sku, p.name, v.price, v.discount_price
        FROM product_variants v
        JOIN products p ON p.id = v.product_id AND p.is_active = 1
        WHERE v.is_active = 1 AND v.id IN ({$placeholders})
    ");
    $stmt->execute(array_values($variantIds));

    $variants = [];
    foreach ($stmt->fetchAll() as $row) {
        $variants[(int) $row['variant_id']] = $row;
    }

    return $variants;
}

/**
 * Условие и параметры поиска Вариантов для страницы «Склад» (FR-STOCK-001):
 * артикул — по префиксу, название Товара — по вхождению; пустой запрос — все.
 *
 * @return array{0: string, 1: array<string, string>}
 */
function productStockSearchCondition(string $query): array
{
    if ($query === '') {
        return ['1 = 1', []];
    }

    $escaped = addcslashes($query, '\%_');

    return [
        '(v.sku LIKE :sku_prefix OR p.name LIKE :name_part)',
        ['sku_prefix' => $escaped . '%', 'name_part' => '%' . $escaped . '%'],
    ];
}

function productVariantCountForStock(string $query): int
{
    [$where, $params] = productStockSearchCondition($query);

    $stmt = getPdo()->prepare("
        SELECT COUNT(*)
        FROM product_variants v
        JOIN products p ON p.id = v.product_id
        WHERE {$where}
    ");
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

/**
 * Варианты для страницы «Склад»: остаток, резерв, время последней
 * синхронизации с МойСклад.
 *
 * @return array<int, array<string, mixed>>
 */
function productVariantListForStock(string $query, int $limit, int $offset): array
{
    [$where, $params] = productStockSearchCondition($query);

    $stmt = getPdo()->prepare("
        SELECT v.id AS variant_id, v.sku, p.name, v.stock_quantity,
               v.reserved_quantity, v.moysklad_synced_at
        FROM product_variants v
        JOIN products p ON p.id = v.product_id
        WHERE {$where}
        ORDER BY p.name ASC, v.id ASC
        LIMIT :row_limit OFFSET :row_offset
    ");
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value);
    }
    $stmt->bindValue('row_limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue('row_offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function productVariantExists(int $variantId): bool
{
    $stmt = getPdo()->prepare('SELECT 1 FROM product_variants WHERE id = :id');
    $stmt->execute(['id' => $variantId]);

    return $stmt->fetchColumn() !== false;
}

/**
 * Записать остаток из МойСклад и время синхронизации (FR-STOCK-001).
 * Резерв не трогаем: он принадлежит Заказам, а не внешней системе.
 */
function productVariantSetStockFromMoySklad(int $variantId, int $quantity): void
{
    $stmt = getPdo()->prepare('
        UPDATE product_variants
        SET stock_quantity = :quantity, moysklad_synced_at = NOW()
        WHERE id = :id
    ');
    $stmt->execute(['quantity' => $quantity, 'id' => $variantId]);
}
