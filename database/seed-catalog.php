<?php

declare(strict_types=1);

/**
 * Сид демо-каталога PetPark — источник данных: лист «Товары» файла
 * 00-input/attachments/catalog-petpark.xlsx (лист «Услуги» того же файла —
 * вне скоупа Фазы 1 Таска 1, заполняется в Фазе 4).
 *
 * Идемпотентно:
 * - категории/бренды/товары — INSERT ... ON DUPLICATE KEY UPDATE по slug;
 * - дочерние строки товара (характеристика вида животного, фото, Варианты,
 *   сид-отзывы) — пересобираются заново на каждый запуск (DELETE по
 *   product_id + INSERT), это безопасно в рамках Фазы 1: корзины/заказов/
 *   избранного, ссылающихся на product_variants, ещё не существует.
 *
 * Запускать из CLI после database/install.php и database/seed.php:
 *   php database/seed-catalog.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Доступ только из командной строки: php database/seed-catalog.php');
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/seed-data/catalog-parser.php';

if (APP_ENV === 'production') {
    fwrite(STDERR, "Сид каталога не запускается при APP_ENV=production.\n");
    exit(1);
}

if (!extension_loaded('gd')) {
    fwrite(STDERR, "Не загружено расширение gd (нужно для демо-фото). Включите в php.ini: extension=gd\n");
    exit(1);
}

// ─── Исходные данные из листа «Товары» (00-input/attachments/catalog-petpark.xlsx) ──

require __DIR__ . '/seed-data/catalog-source.php';
require __DIR__ . '/seed-data/reviews-source.php';

// Ручной отбор «Хитов продаж» (FR-HOME-005) и popularity_rank для сортировки
// «по популярности» (FR-CAT-004) — Владельцем, не вычисляется алгоритмом
// (см. ADR в .docs/planning-log.md). По 1-2 товара на каждую из 5 категорий.
const FEATURED_RANKS = [
    'FD-01' => 1, // ВетЛайн Стерилайз — Корма
    'TR-02' => 2, // Жевательные косточки — Лакомства
    'AC-10' => 3, // Домик-когтеточка КотоDom Tower — Аксессуары
    'FD-07' => 4, // ZOOBALANCE Grain-Free — Корма
    'TY-01' => 5, // Мышка с кошачьей мятой — Игрушки
    'AC-05' => 6, // Шлейка Лапки&Хвостики Актив — Аксессуары
    'HY-03' => 7, // Наполнитель комкующийся — Средства гигиены
    'TY-07' => 8, // Миска-головоломка КотоDom Puzzle — Игрушки
];

// Товары, у которых намеренно обнулён остаток единственного Варианта —
// демонстрируют состояние «Нет в наличии» (dod-global.md, «Данные»:
// нужны Варианты с остатком 0/1-4/≥5, естественное распределение остатка
// xlsx редко даёт 0 само по себе).
const FORCED_ZERO_STOCK_CODES = ['FD-16', 'AC-11'];

const SPECIES_GENITIVE = [
    'Кошка'        => 'кошек',
    'Собака'       => 'собак',
    'Птица'        => 'птиц',
    'Универсально' => 'кошек и собак',
];

// Цветовые ключевые слова из «Описание для генерации изображения» —
// только для подбора фона демо-фото (не для текста Товара, см. ниже).
const PLACEHOLDER_COLOR_KEYWORDS = [
    'бежев'        => [222, 201, 168],
    'зелен'        => [139, 195, 74],
    'зелён'        => [139, 195, 74],
    'тёмно-син'    => [40, 62, 112],
    'темно-син'    => [40, 62, 112],
    'син'          => [63, 105, 170],
    'оранж'        => [255, 152, 0],
    'коричнев'     => [121, 85, 72],
    'розов'        => [244, 143, 177],
    'голуб'        => [79, 195, 247],
    'сер'          => [158, 158, 158],
    'жёлт'         => [255, 213, 79],
    'желт'         => [255, 213, 79],
    'бел'          => [235, 235, 232],
    'чёрн'         => [66, 66, 66],
    'черн'         => [66, 66, 66],
    'красн'        => [229, 57, 53],
    'фиолет'       => [156, 39, 176],
    'бирюз'        => [0, 150, 136],
    'пастель'      => [255, 224, 178],
    'мят'          => [178, 223, 219],
    'золот'        => [212, 175, 55],
];

const PLACEHOLDER_CATEGORY_COLORS = [
    'Корма'             => [174, 213, 129],
    'Лакомства'         => [255, 183, 77],
    'Аксессуары'        => [100, 181, 246],
    'Игрушки'           => [255, 138, 101],
    'Средства гигиены'  => [77, 208, 225],
];

const ROOT_CATEGORY_ORDER = [
    'Корма'             => 1,
    'Лакомства'         => 2,
    'Аксессуары'        => 3,
    'Игрушки'           => 4,
    'Средства гигиены'  => 5,
];

// ─── Справочники (категории/бренды) ────────────────────────────────────

function fetchUserIdByEmail(PDO $pdo, string $email): ?int
{
    $statement = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $statement->execute(['email' => $email]);
    $id = $statement->fetchColumn();

    return $id !== false ? (int) $id : null;
}

/**
 * @param array<string, int> $cache ключ "parentId|slug" -> id, экономит запросы в рамках одного запуска
 */
function upsertCategory(PDO $pdo, array &$cache, string $name, ?int $parentId, int $sortOrder): int
{
    $slug     = slugify($name);
    $cacheKey = ($parentId ?? 0) . '|' . $slug;

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $statement = $pdo->prepare('
        INSERT INTO categories (parent_id, name, slug, sort_order)
        VALUES (:parent_id, :name, :slug, :sort_order)
        ON DUPLICATE KEY UPDATE
            id         = LAST_INSERT_ID(id),
            name       = VALUES(name),
            parent_id  = VALUES(parent_id),
            sort_order = VALUES(sort_order)
    ');
    $statement->execute([
        'parent_id'  => $parentId,
        'name'       => $name,
        'slug'       => $slug,
        'sort_order' => $sortOrder,
    ]);

    $id = (int) $pdo->lastInsertId();
    $cache[$cacheKey] = $id;

    return $id;
}

/**
 * @param array<string, int> $cache
 */
function upsertBrand(PDO $pdo, array &$cache, string $name): int
{
    if (isset($cache[$name])) {
        return $cache[$name];
    }

    $statement = $pdo->prepare('
        INSERT INTO brands (name, slug) VALUES (:name, :slug)
        ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), name = VALUES(name)
    ');
    $statement->execute(['name' => $name, 'slug' => slugify($name)]);

    $id = (int) $pdo->lastInsertId();
    $cache[$name] = $id;

    return $id;
}

// ─── Товар и его дочерние данные ────────────────────────────────────────

function buildProductDescription(array $row): string
{
    $species = SPECIES_GENITIVE[$row['species']] ?? mb_strtolower($row['species']);

    return sprintf(
        '%s — %s. Подходит для: %s. Бренд: %s. Варианты: %s.',
        $row['name'],
        $row['subcategory'],
        $species,
        $row['brand'],
        $row['variants_raw']
    );
}

function upsertProduct(PDO $pdo, int $categoryId, ?int $brandId, string $name, string $slug, string $description, ?int $popularityRank): int
{
    $statement = $pdo->prepare('
        INSERT INTO products (category_id, brand_id, name, slug, description, is_active, is_featured, popularity_rank)
        VALUES (:category_id, :brand_id, :name, :slug, :description, 1, :is_featured, :popularity_rank)
        ON DUPLICATE KEY UPDATE
            id              = LAST_INSERT_ID(id),
            category_id     = VALUES(category_id),
            brand_id        = VALUES(brand_id),
            name            = VALUES(name),
            description     = VALUES(description),
            is_featured     = VALUES(is_featured),
            popularity_rank = VALUES(popularity_rank)
    ');
    $statement->execute([
        'category_id'     => $categoryId,
        'brand_id'        => $brandId,
        'name'            => $name,
        'slug'            => $slug,
        'description'     => $description,
        'is_featured'     => $popularityRank !== null ? 1 : 0,
        'popularity_rank' => $popularityRank,
    ]);

    return (int) $pdo->lastInsertId();
}

function replaceProductSpeciesAttribute(PDO $pdo, int $productId, string $species): void
{
    $pdo->prepare('DELETE FROM product_attributes WHERE product_id = :product_id AND attr_name = :attr_name')
        ->execute(['product_id' => $productId, 'attr_name' => 'вид_животного']);

    $pdo->prepare('
        INSERT INTO product_attributes (product_id, attr_name, attr_value)
        VALUES (:product_id, :attr_name, :attr_value)
    ')->execute([
        'product_id' => $productId,
        'attr_name'  => 'вид_животного',
        'attr_value' => $species,
    ]);
}

function replaceMainProductImage(PDO $pdo, int $productId, string $path): void
{
    $pdo->prepare('DELETE FROM product_images WHERE product_id = :product_id')
        ->execute(['product_id' => $productId]);

    $pdo->prepare('
        INSERT INTO product_images (product_id, path, sort_order, is_main)
        VALUES (:product_id, :path, 0, 1)
    ')->execute(['product_id' => $productId, 'path' => $path]);
}

/**
 * Пересобирает Варианты товара из строки «Варианты (вес/вкус/размер)»:
 * удаляет старые (каскадно чистит product_variant_attributes по FK) и
 * вставляет заново по разбору parseVariantDimensions()/buildVariantCombinations().
 *
 * @return int количество вставленных Вариантов
 */
function replaceProductVariants(PDO $pdo, int $productId, array $row, int $globalVariantOffset): int
{
    $pdo->prepare('DELETE FROM product_variants WHERE product_id = :product_id')
        ->execute(['product_id' => $productId]);

    $dimensions   = parseVariantDimensions($row['variants_raw'], $row['category']);
    $combinations = buildVariantCombinations($dimensions);
    $stocks       = distributeStock((int) $row['stock'], count($combinations));

    if (in_array($row['code'], FORCED_ZERO_STOCK_CODES, true)) {
        $stocks[count($stocks) - 1] = 0;
    }

    $insertVariant = $pdo->prepare('
        INSERT INTO product_variants (product_id, sku, price, discount_price, stock_quantity, is_active)
        VALUES (:product_id, :sku, :price, :discount_price, :stock_quantity, 1)
    ');
    $insertAttribute = $pdo->prepare('
        INSERT INTO product_variant_attributes (variant_id, attr_name, attr_value)
        VALUES (:variant_id, :attr_name, :attr_value)
    ');

    foreach ($combinations as $i => $combo) {
        $multiplier = variantPriceMultiplier($combo, $dimensions);
        $price      = roundToNearestTen((float) $row['price'] * $multiplier);

        // Каждый 6-й Вариант каталога (глобальный счётчик) — со Скидкой,
        // чтобы в данных были Варианты и с discount_price, и без (DoD).
        $globalIndex   = $globalVariantOffset + $i;
        $discountPrice = ($globalIndex % 6 === 5) ? roundToNearestTen($price * 0.85) : null;

        $insertVariant->execute([
            'product_id'     => $productId,
            'sku'            => sprintf('%s-%02d', $row['code'], $i + 1),
            'price'          => $price,
            'discount_price' => $discountPrice,
            'stock_quantity' => $stocks[$i],
        ]);
        $variantId = (int) $pdo->lastInsertId();

        foreach ($combo as $attrName => $attrValue) {
            $insertAttribute->execute([
                'variant_id' => $variantId,
                'attr_name'  => $attrName,
                'attr_value' => $attrValue,
            ]);
        }
    }

    return count($combinations);
}

function replaceSeedReviews(PDO $pdo, int $productId, array $reviews, ?int $moderatorId): int
{
    $pdo->prepare("DELETE FROM reviews WHERE product_id = :product_id AND author_email LIKE '%@seed.petpark.test'")
        ->execute(['product_id' => $productId]);

    $insert = $pdo->prepare('
        INSERT INTO reviews (product_id, author_name, author_email, rating, body, status, moderated_by_user_id, moderated_at)
        VALUES (:product_id, :author_name, :author_email, :rating, :body, :status, :moderated_by_user_id, :moderated_at)
    ');

    foreach ($reviews as $review) {
        $isPublished = $review['status'] === 'published';
        $insert->execute([
            'product_id'           => $productId,
            'author_name'          => $review['author_name'],
            'author_email'         => $review['author_email'],
            'rating'               => $review['rating'],
            'body'                 => $review['body'],
            'status'               => $review['status'],
            'moderated_by_user_id' => $isPublished ? $moderatorId : null,
            'moderated_at'         => $isPublished ? date('Y-m-d H:i:s') : null,
        ]);
    }

    return count($reviews);
}

// ─── Демо-фото товара (public/uploads/products/) ────────────────────────

function detectPlaceholderColor(string $prompt, string $category): array
{
    $lower = mb_strtolower($prompt);

    foreach (PLACEHOLDER_COLOR_KEYWORDS as $keyword => $rgb) {
        if (str_contains($lower, $keyword)) {
            return $rgb;
        }
    }

    return PLACEHOLDER_CATEGORY_COLORS[$category] ?? [189, 189, 189];
}

function drawCategoryShape(GdImage $image, int $color, string $category, int $width, int $height): void
{
    $cx = intdiv($width, 2);
    $cy = intdiv($height, 2);

    match ($category) {
        'Корма'            => imagefilledellipse($image, $cx, $cy, 260, 260, $color),
        'Лакомства'        => imagefilledpolygon($image, [$cx, $cy - 150, $cx - 150, $cy + 120, $cx + 150, $cy + 120], $color),
        'Аксессуары'       => imagefilledrectangle($image, $cx - 130, $cy - 130, $cx + 130, $cy + 130, $color),
        'Игрушки'          => drawStarShape($image, $cx, $cy, 150, 70, $color),
        'Средства гигиены' => imagefilledellipse($image, $cx, $cy, 180, 260, $color),
        default            => imagefilledellipse($image, $cx, $cy, 220, 220, $color),
    };
}

function drawStarShape(GdImage $image, int $cx, int $cy, int $outerRadius, int $innerRadius, int $color): void
{
    $points = [];
    for ($i = 0; $i < 10; $i++) {
        $radius  = $i % 2 === 0 ? $outerRadius : $innerRadius;
        $angle   = -M_PI / 2 + $i * M_PI / 5;
        $points[] = $cx + (int) round($radius * cos($angle));
        $points[] = $cy + (int) round($radius * sin($angle));
    }
    imagefilledpolygon($image, $points, $color);
}

/**
 * Генерирует демо-фото товара один раз (идемпотентно — пропускает
 * существующий файл). Не фотореалистичная генерация нейросетью (недоступна
 * в этом окружении) — абстрактная карточка: фон подобран по цветовым словам
 * из «Описание для генерации изображения», форма — по Категории товара.
 * И название, и категория, и описание участвуют в результате — этим текст
 * этой колонки не единственный источник для генерации фото.
 */
function ensureProductPlaceholderImage(string $absolutePath, string $category, string $imagePrompt): void
{
    if (is_file($absolutePath)) {
        return;
    }

    $dir = dirname($absolutePath);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException("Не удалось создать директорию: {$dir}");
    }

    $width  = 600;
    $height = 600;
    $image  = imagecreatetruecolor($width, $height);

    [$r, $g, $b] = detectPlaceholderColor($imagePrompt, $category);
    $background  = imagecolorallocate($image, $r, $g, $b);
    imagefill($image, 0, 0, $background);

    $cardColor = imagecolorallocate($image, 250, 250, 248);
    imagefilledrectangle($image, 50, 50, $width - 50, $height - 50, $cardColor);

    $accent = imagecolorallocate($image, (int) ($r * 0.7), (int) ($g * 0.7), (int) ($b * 0.7));
    drawCategoryShape($image, $accent, $category, $width, $height);

    imagepng($image, $absolutePath, 6);
    imagedestroy($image);
}

// ─── Основной проход по CATALOG_SOURCE ──────────────────────────────────

$pdo = getPdo();

$moderatorId = fetchUserIdByEmail($pdo, 'shift-admin@petpark.test')
    ?? fetchUserIdByEmail($pdo, 'owner@petpark.test');

if ($moderatorId === null) {
    fwrite(STDERR, "Не найден пользователь shift_admin/owner — сначала выполните: php database/seed.php\n");
    exit(1);
}

$categoryCache  = [];
$brandCache     = [];
$subSortCounter = [];
$variantOffset  = 0;
$productCount   = 0;
$variantCount   = 0;
$reviewCount    = 0;

foreach (CATALOG_SOURCE as $row) {
    $pdo->beginTransaction();

    try {
        $rootSortOrder = ROOT_CATEGORY_ORDER[$row['category']] ?? 0;
        $rootCategoryId = upsertCategory($pdo, $categoryCache, $row['category'], null, $rootSortOrder);

        $subSortCounter[$row['category']] = ($subSortCounter[$row['category']] ?? 0) + 1;
        $subCategoryId = upsertCategory(
            $pdo,
            $categoryCache,
            $row['subcategory'],
            $rootCategoryId,
            $subSortCounter[$row['category']]
        );

        $brandId = upsertBrand($pdo, $brandCache, $row['brand']);

        // slug из «Название + Подкатегория» — в источнике несколько товаров
        // делят одно и то же «Название» (например, два SKU «Мясной Двор»
        // под разными Подкатегориями), одного «Название» для уникальности
        // slug недостаточно.
        $productSlug = slugify($row['name'] . ' ' . $row['subcategory']);
        $description = buildProductDescription($row);

        $productId = upsertProduct(
            $pdo,
            $subCategoryId,
            $brandId,
            $row['name'],
            $productSlug,
            $description,
            FEATURED_RANKS[$row['code']] ?? null
        );

        replaceProductSpeciesAttribute($pdo, $productId, $row['species']);

        $imageRelativePath = 'products/' . $productSlug . '.png';
        ensureProductPlaceholderImage(
            ROOT_PATH . '/public/uploads/' . $imageRelativePath,
            $row['category'],
            $row['image_prompt']
        );
        replaceMainProductImage($pdo, $productId, $imageRelativePath);

        $variantsInserted = replaceProductVariants($pdo, $productId, $row, $variantOffset);
        $variantOffset += $variantsInserted;
        $variantCount  += $variantsInserted;

        $reviewCount += replaceSeedReviews($pdo, $productId, REVIEWS_SOURCE[$row['code']] ?? [], $moderatorId);

        $productCount++;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $pdo->commit();
}

echo "✅ Каталог создан/обновлён: {$productCount} товаров, {$variantCount} вариантов, {$reviewCount} отзывов, " .
    count($categoryCache) . " категорий (со вложенными), " . count($brandCache) . " брендов.\n";
