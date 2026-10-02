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
 * Записывает подтверждённую Характеристику Товара: существующая с тем же
 * названием заменяется, дубля нет (UNIQUE на таблице нет). Атомарность
 * обеспечивает вызывающий — функция зовётся внутри его транзакции.
 */
function productAttributeReplace(int $productId, string $name, string $value): void
{
    $pdo = getPdo();

    $pdo->prepare('DELETE FROM product_attributes WHERE product_id = ? AND attr_name = ?')
        ->execute([$productId, $name]);
    $pdo->prepare('INSERT INTO product_attributes (product_id, attr_name, attr_value) VALUES (?, ?, ?)')
        ->execute([$productId, $name, $value]);
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
 * Фото Товара для галереи Карточки: главное первым, затем по sort_order.
 *
 * @return array<int, string> пути относительно public/uploads/
 */
function productImagePaths(int $productId): array
{
    $stmt = getPdo()->prepare('
        SELECT path
        FROM product_images
        WHERE product_id = ?
        ORDER BY is_main DESC, sort_order ASC, id ASC
    ');
    $stmt->execute([$productId]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
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

// ─── ИИ-описания (FR-AI-002, phase-5 Таск 5) ────────────────────────────
// Черновик живёт в products.description_draft; description меняет только
// productDescriptionPublish() — решение Владельца.

/**
 * Данные Товара для генерации: только то, что допустимо отдать ИИ (название,
 * Категория, бренд) — цена, остаток и отзывы в выборку не попадают.
 *
 * @return array{id: int, name: string, category_name: string, brand_name: string|null}|null
 */
function productDescriptionSource(int $productId): ?array
{
    $stmt = getPdo()->prepare('
        SELECT p.id, p.name, c.name AS category_name, b.name AS brand_name
        FROM products p
        JOIN categories c ON c.id = p.category_id
        LEFT JOIN brands b ON b.id = p.brand_id
        WHERE p.id = ?
    ');
    $stmt->execute([$productId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Подтверждённые Характеристики Товара (product_attributes) — не черновики.
 *
 * @return array<string, string> attr_name => значение
 */
function productConfirmedAttributes(int $productId): array
{
    $stmt = getPdo()->prepare('SELECT attr_name, attr_value FROM product_attributes WHERE product_id = ? ORDER BY attr_name, id');
    $stmt->execute([$productId]);

    $attributes = [];
    foreach ($stmt->fetchAll() as $row) {
        $attributes[(string) $row['attr_name']] = (string) $row['attr_value'];
    }

    return $attributes;
}

/** Записывает (заменяет) только черновик — description не трогается. */
function productDescriptionDraftSave(int $productId, string $draft): void
{
    getPdo()->prepare('UPDATE products SET description_draft = ? WHERE id = ?')
        ->execute([$draft, $productId]);
}

/**
 * Публикация: текст (черновик или его правка) → description, черновик
 * обнуляется, исход пишется в ai_draft_outcomes — всё атомарно. Публикуется
 * только при открытом черновике (FOR UPDATE): повтор формы ничего не меняет.
 *
 * @param string $text нормализованный итоговый текст
 * @return string|null исход (accepted / edited); null — открытого черновика нет
 */
function productDescriptionPublish(int $productId, string $text): ?string
{
    $pdo = getPdo();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT description_draft FROM products WHERE id = ? FOR UPDATE');
        $stmt->execute([$productId]);
        $draft = $stmt->fetchColumn();

        if (!is_string($draft)) {
            $pdo->rollBack();

            return null;
        }

        $outcome = descriptionNormalize($draft) === $text ? 'accepted' : 'edited';

        $pdo->prepare('UPDATE products SET description = ?, description_draft = NULL WHERE id = ?')
            ->execute([$text, $productId]);
        $pdo->prepare("INSERT INTO ai_draft_outcomes (kind, ref_id, outcome) VALUES ('description', ?, ?)")
            ->execute([$productId, $outcome]);

        $pdo->commit();

        return $outcome;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Отклонение черновика: обнуляет его и пишет исход rejected.
 *
 * @return bool false — открытого черновика нет
 */
function productDescriptionDiscard(int $productId): bool
{
    $pdo = getPdo();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE products SET description_draft = NULL WHERE id = ? AND description_draft IS NOT NULL');
        $stmt->execute([$productId]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();

            return false;
        }

        $pdo->prepare("INSERT INTO ai_draft_outcomes (kind, ref_id, outcome) VALUES ('description', ?, 'rejected')")
            ->execute([$productId]);

        $pdo->commit();

        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Очередь пакета: активные Товары Категорий без текущего черновика.
 * Плейсхолдеры: N Категорий.
 *
 * @param list<int> $categoryIds
 */
function productDescriptionQueueWhere(array $categoryIds): string
{
    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

    return "p.is_active = 1 AND p.description_draft IS NULL AND p.category_id IN ({$placeholders})";
}

/**
 * Порция очереди. Курсор id > $afterId — Товар с ошибкой не берётся повторно
 * в том же запуске.
 *
 * @param list<int> $categoryIds
 * @return array<int, array{id: int, name: string}>
 */
function productDescriptionQueue(array $categoryIds, int $afterId, int $limit): array
{
    if ($categoryIds === []) {
        return [];
    }

    $stmt = getPdo()->prepare(
        'SELECT p.id, p.name FROM products p
         WHERE p.id > ? AND ' . productDescriptionQueueWhere($categoryIds) . '
         ORDER BY p.id LIMIT ?'
    );
    $position = 1;
    $stmt->bindValue($position++, $afterId, PDO::PARAM_INT);
    foreach ($categoryIds as $categoryId) {
        $stmt->bindValue($position++, $categoryId, PDO::PARAM_INT);
    }
    $stmt->bindValue($position, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/** @param list<int> $categoryIds */
function productDescriptionQueueCount(array $categoryIds): int
{
    if ($categoryIds === []) {
        return 0;
    }

    $stmt = getPdo()->prepare('SELECT COUNT(*) FROM products p WHERE ' . productDescriptionQueueWhere($categoryIds));
    $stmt->execute($categoryIds);

    return (int) $stmt->fetchColumn();
}

/** Товаров с открытым черновиком описания. */
function productDescriptionDraftCount(): int
{
    return (int) getPdo()->query('SELECT COUNT(*) FROM products WHERE description_draft IS NOT NULL')->fetchColumn();
}

/**
 * Условие списка страницы: Категории (null — все) и/или только с черновиком.
 *
 * @param list<int>|null $categoryIds
 * @return array{0: string, 1: list<int>} условие и значения плейсхолдеров
 */
function productDescriptionListWhere(?array $categoryIds, bool $draftsOnly): array
{
    $conditions = ['1 = 1'];
    $params = [];

    if ($categoryIds !== null) {
        $conditions[] = 'p.category_id IN (' . implode(',', array_fill(0, count($categoryIds), '?')) . ')';
        array_push($params, ...$categoryIds);
    }
    if ($draftsOnly) {
        $conditions[] = 'p.description_draft IS NOT NULL';
    }

    return [implode(' AND ', $conditions), $params];
}

/** @param list<int>|null $categoryIds */
function productDescriptionListCount(?array $categoryIds, bool $draftsOnly): int
{
    if ($categoryIds === []) {
        return 0;
    }

    [$where, $params] = productDescriptionListWhere($categoryIds, $draftsOnly);
    $stmt = getPdo()->prepare("SELECT COUNT(*) FROM products p WHERE {$where}");
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

/**
 * Страница Товаров для экрана генерации — с текущим описанием и черновиком.
 *
 * @param list<int>|null $categoryIds
 * @return array<int, array{id: int, name: string, description: string|null, description_draft: string|null, category_name: string}>
 */
function productDescriptionList(?array $categoryIds, bool $draftsOnly, int $limit, int $offset): array
{
    if ($categoryIds === []) {
        return [];
    }

    [$where, $params] = productDescriptionListWhere($categoryIds, $draftsOnly);
    $stmt = getPdo()->prepare(
        "SELECT p.id, p.name, p.description, p.description_draft, c.name AS category_name
         FROM products p
         JOIN categories c ON c.id = p.category_id
         WHERE {$where}
         ORDER BY p.id LIMIT ? OFFSET ?"
    );
    $position = 1;
    foreach ($params as $param) {
        $stmt->bindValue($position++, $param, PDO::PARAM_INT);
    }
    $stmt->bindValue($position++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($position, $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Подбор активных Товаров под вопрос в чате-консультанте (FR-AI-003): чем больше
 * основ слов запроса встретилось в названии, Категории, бренде или значении
 * Характеристики — тем выше. Без точного совпадения вернёт самый похожий
 * (хотя бы одна основа). Цена и остаток — живые, из БД: цена «от» среди
 * доступных Вариантов (если все распроданы — среди всех активных).
 *
 * @param list<string> $tokens основы слов (consultantSearchTokens())
 * @return array<int, array{name: string, slug: string, price_from: string, variants_count: int|string, available: int|string}>
 */
function productsSearchForChat(array $tokens, int $limit): array
{
    if ($tokens === []) {
        return [];
    }

    $scoreParts = [];
    $params = [];
    foreach ($tokens as $token) {
        $like = '%' . addcslashes($token, '\%_') . '%';
        $scoreParts[] = '(p.name LIKE ? OR c.name LIKE ? OR b.name LIKE ? OR EXISTS (
            SELECT 1 FROM product_attributes a WHERE a.product_id = p.id AND a.attr_value LIKE ?
        ))';
        array_push($params, $like, $like, $like, $like);
    }
    $score = implode(' + ', $scoreParts);

    $stmt = getPdo()->prepare("
        SELECT p.name, p.slug,
               {$score} AS score,
               COALESCE(
                   (SELECT MIN(COALESCE(v.discount_price, v.price)) FROM product_variants v
                    WHERE v.product_id = p.id AND v.is_active = 1 AND v.stock_quantity - v.reserved_quantity > 0),
                   (SELECT MIN(COALESCE(v.discount_price, v.price)) FROM product_variants v
                    WHERE v.product_id = p.id AND v.is_active = 1)
               ) AS price_from,
               (SELECT COUNT(*) FROM product_variants v
                WHERE v.product_id = p.id AND v.is_active = 1) AS variants_count,
               (SELECT COALESCE(SUM(GREATEST(v.stock_quantity - v.reserved_quantity, 0)), 0)
                FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1) AS available
        FROM products p
        JOIN categories c ON c.id = p.category_id
        LEFT JOIN brands b ON b.id = p.brand_id
        WHERE p.is_active = 1
        HAVING score > 0 AND price_from IS NOT NULL
        ORDER BY score DESC, (available > 0) DESC, p.popularity_rank IS NULL, p.popularity_rank ASC, p.id ASC
        LIMIT ?
    ");
    foreach ($params as $i => $value) {
        $stmt->bindValue($i + 1, $value);
    }
    $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Варианты-кандидаты для черновика Заказа из Обращения (FR-AI-004, Q-060):
 * чем больше основ слов запроса встретилось в названии Товара, Категории,
 * бренде, подтверждённой Характеристике или Характеристике Варианта (вес,
 * вкус) — тем выше. `product_attributes` содержит только подтверждённые
 * значения (черновики лежат отдельно), поэтому неподтверждённое сюда не попадает.
 * Цена — живая, из БД (со скидкой, если есть).
 *
 * @param list<string> $tokens основы слов (consultantSearchTokens())
 * @return array<int, array{variant_id: int|string, name: string, price: string, score: int|string}>
 */
function productVariantCandidates(array $tokens, int $limit): array
{
    if ($tokens === []) {
        return [];
    }

    $scoreParts = [];
    $params = [];
    foreach ($tokens as $token) {
        $like = '%' . addcslashes($token, '\%_') . '%';
        $scoreParts[] = '(p.name LIKE ? OR c.name LIKE ? OR b.name LIKE ?
            OR EXISTS (SELECT 1 FROM product_attributes a WHERE a.product_id = p.id AND a.attr_value LIKE ?)
            OR EXISTS (SELECT 1 FROM product_variant_attributes va WHERE va.variant_id = v.id AND va.attr_value LIKE ?))';
        array_push($params, $like, $like, $like, $like, $like);
    }
    $score = implode(' + ', $scoreParts);

    $stmt = getPdo()->prepare("
        SELECT v.id AS variant_id, p.name, COALESCE(v.discount_price, v.price) AS price, {$score} AS score
        FROM product_variants v
        JOIN products p ON p.id = v.product_id AND p.is_active = 1
        JOIN categories c ON c.id = p.category_id
        LEFT JOIN brands b ON b.id = p.brand_id
        WHERE v.is_active = 1
        HAVING score > 0
        ORDER BY score DESC, p.id ASC, v.id ASC
        LIMIT ?
    ");
    foreach ($params as $i => $value) {
        $stmt->bindValue($i + 1, $value);
    }
    $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Сколько активных Товаров имеют хотя бы одну подтверждённую Характеристику —
 * от этого зависит точность сопоставления в черновике Заказа (Q-060).
 *
 * @return array{total: int, confirmed: int}
 */
function productConfirmedAttributesCoverage(): array
{
    $row = getPdo()->query('
        SELECT COUNT(*) AS total,
               COALESCE(SUM(EXISTS (SELECT 1 FROM product_attributes a WHERE a.product_id = p.id)), 0) AS confirmed
        FROM products p
        WHERE p.is_active = 1
    ')->fetch();

    return ['total' => (int) $row['total'], 'confirmed' => (int) $row['confirmed']];
}

// ─── Список Товаров в админке (FR-ADM-001, phase-7 Таск 7) ───────────────

/**
 * Общее WHERE списка Товаров в админке: подстрока в названии, основная
 * Категория, статус (`active`/`inactive`; пусто — все). Единственное место
 * сборки условий — для productAdminCount() и productAdminList().
 *
 * @return array{0: string, 1: array<string, mixed>}
 */
function productAdminConditions(string $query, int $categoryId, string $status): array
{
    $conditions = ['1 = 1'];
    $params = [];

    if ($query !== '') {
        $conditions[] = 'p.name LIKE :name_part';
        $params['name_part'] = '%' . addcslashes($query, '\%_') . '%';
    }
    if ($categoryId > 0) {
        $conditions[] = 'p.category_id = :category_id';
        $params['category_id'] = $categoryId;
    }
    if ($status === 'active') {
        $conditions[] = 'p.is_active = 1';
    } elseif ($status === 'inactive') {
        $conditions[] = 'p.is_active = 0';
    }

    return [implode(' AND ', $conditions), $params];
}

function productAdminCount(string $query, int $categoryId, string $status): int
{
    [$where, $params] = productAdminConditions($query, $categoryId, $status);

    $stmt = getPdo()->prepare("SELECT COUNT(*) FROM products p WHERE {$where}");
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

/**
 * Страница списка Товаров для админки: цена — диапазон эффективной цены
 * активных Вариантов (как на витрине), наличие — свободный остаток
 * активных Вариантов, статус Характеристик — `drafts` (есть открытые
 * черновики ИИ) / `confirmed` / `none`. Агрегаты — одним запросом.
 *
 * @return array<int, array<string, mixed>>
 */
function productAdminList(string $query, int $categoryId, string $status, int $limit, int $offset): array
{
    [$where, $params] = productAdminConditions($query, $categoryId, $status);

    $stmt = getPdo()->prepare("
        SELECT
            p.id, p.name, p.is_active,
            c.name AS category_name,
            b.name AS brand_name,
            v.price_min, v.price_max, v.available_quantity,
            img.path AS image_path,
            CASE
                WHEN EXISTS (
                    SELECT 1 FROM product_attribute_drafts d
                    WHERE d.product_id = p.id AND d.status IN ('pending', 'needs_decision')
                ) THEN 'drafts'
                WHEN EXISTS (
                    SELECT 1 FROM product_attributes a WHERE a.product_id = p.id
                ) THEN 'confirmed'
                ELSE 'none'
            END AS attributes_status
        FROM products p
        JOIN categories c ON c.id = p.category_id
        LEFT JOIN brands b ON b.id = p.brand_id
        LEFT JOIN (
            SELECT product_id,
                   MIN(IFNULL(discount_price, price)) AS price_min,
                   MAX(IFNULL(discount_price, price)) AS price_max,
                   SUM(GREATEST(stock_quantity - reserved_quantity, 0)) AS available_quantity
            FROM product_variants
            WHERE is_active = 1
            GROUP BY product_id
        ) v ON v.product_id = p.id
        LEFT JOIN (
            SELECT product_id, path,
                   ROW_NUMBER() OVER (
                       PARTITION BY product_id
                       ORDER BY is_main DESC, sort_order ASC, id ASC
                   ) AS rn
            FROM product_images
        ) img ON img.product_id = p.id AND img.rn = 1
        WHERE {$where}
        ORDER BY p.name ASC, p.id ASC
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
