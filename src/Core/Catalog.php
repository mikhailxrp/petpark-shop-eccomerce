<?php

declare(strict_types=1);

/**
 * Чистые функции листинга каталога — без БД, без HTML (php.md, тестируются
 * юнитами). SQL — в src/Models/Product.php и src/Models/Category.php.
 */

if (!defined('CATALOG_PER_PAGE')) {
    define('CATALOG_PER_PAGE', 12);
}

if (!defined('CATALOG_LOW_STOCK_THRESHOLD')) {
    // Порог «В наличии» → «Осталось мало», ответ Q-044 (меньше 5 штук).
    define('CATALOG_LOW_STOCK_THRESHOLD', 5);
}

const CATALOG_SORT_OPTIONS = ['popularity', 'price_asc', 'price_desc', 'new'];

// Подписи сортировки — общие для полной страницы (catalog.php) и
// AJAX-фрагмента (components/catalog-results.php), одним списком.
const CATALOG_SORT_LABELS = [
    'popularity' => 'По популярности',
    'price_asc'  => 'Сначала дешевле',
    'price_desc' => 'Сначала дороже',
    'new'        => 'Сначала новые',
];

// Заглушка для Товара без фото в product_images.
const CATALOG_IMAGE_FALLBACK = '/assets/img/food-1.png';

/**
 * URL фото Товара из product_images.path (относительно public/uploads/);
 * нет фото — заглушка.
 */
function catalogImageUrl(?string $path): string
{
    return $path !== null && $path !== '' ? '/uploads/' . ltrim($path, '/') : CATALOG_IMAGE_FALLBACK;
}

function catalogNormalizeSort(mixed $sort): string
{
    return is_string($sort) && in_array($sort, CATALOG_SORT_OPTIONS, true) ? $sort : 'popularity';
}

function catalogNormalizePage(mixed $page): int
{
    if (is_string($page) && ctype_digit($page)) {
        $page = (int) $page;
    }

    return is_int($page) && $page >= 1 ? $page : 1;
}

/**
 * Доступность Варианта товара (FR-CAT-007): три состояния без точного
 * количества, считается от stock_quantity - reserved_quantity.
 */
function catalogAvailabilityStatus(
    int $stockQuantity,
    int $reservedQuantity,
    int $threshold = CATALOG_LOW_STOCK_THRESHOLD
): string {
    $available = $stockQuantity - $reservedQuantity;

    return match (true) {
        $available <= 0 => 'out',
        $available < $threshold => 'low',
        default => 'in',
    };
}

function catalogAvailabilityLabel(string $status): string
{
    return match ($status) {
        'out' => 'Нет в наличии',
        'low' => 'Осталось мало',
        'in' => 'В наличии',
        default => throw new InvalidArgumentException("Неизвестный статус наличия: {$status}"),
    };
}

/**
 * Скидка — одна вручную заданная цена (BR-002), не процент: если задана —
 * действует, иначе действует обычная цена.
 */
function catalogEffectivePrice(float $price, ?float $discountPrice): float
{
    return $discountPrice !== null ? $discountPrice : $price;
}

/**
 * Выбор Варианта товара для Карточки (FR-CARD-001): запрошенный
 * `?variant=` id, если он есть среди переданных Вариантов, иначе —
 * Вариант с минимальной эффективной ценой (тот же принцип, что
 * каталожная цена листинга, database.md). $variants должны уже быть
 * отфильтрованы по `is_active = 1` на уровне Model — сюда неактивные
 * не попадают, поэтому запрос на неактивный/чужой/несуществующий id
 * не найдёт совпадения и просто попадёт в дефолт.
 *
 * @param array<int, array{id: int, price: float, discount_price: ?float}> $variants Только активные Варианты одного Товара
 */
function catalogSelectVariant(array $variants, int|string|null $requestedVariantId): ?array
{
    if ($variants === []) {
        return null;
    }

    if ($requestedVariantId !== null && $requestedVariantId !== '') {
        $requestedId = is_int($requestedVariantId)
            ? $requestedVariantId
            : (ctype_digit((string) $requestedVariantId) ? (int) $requestedVariantId : null);

        if ($requestedId !== null) {
            foreach ($variants as $variant) {
                if ((int) $variant['id'] === $requestedId) {
                    return $variant;
                }
            }
        }
    }

    $default = $variants[0];
    $defaultPrice = catalogEffectivePrice(
        (float) $default['price'],
        $default['discount_price'] !== null ? (float) $default['discount_price'] : null
    );

    foreach ($variants as $variant) {
        $price = catalogEffectivePrice(
            (float) $variant['price'],
            $variant['discount_price'] !== null ? (float) $variant['discount_price'] : null
        );

        if ($price < $defaultPrice || ($price === $defaultPrice && (int) $variant['id'] < (int) $default['id'])) {
            $default = $variant;
            $defaultPrice = $price;
        }
    }

    return $default;
}

/**
 * Дерево Категорий из плоского списка (parent_id) — для сайдбара каталога.
 *
 * @param array<int, array{id: int, parent_id: ?int}> $categories
 * @return array<int, array<string, mixed>>
 */
function catalogBuildCategoryTree(array $categories): array
{
    $byParent = [];
    foreach ($categories as $category) {
        $byParent[$category['parent_id'] ?? 0][] = $category;
    }

    $build = static function (int $parentId) use (&$build, $byParent): array {
        $nodes = $byParent[$parentId] ?? [];
        foreach ($nodes as &$node) {
            $node['children'] = $build((int) $node['id']);
        }
        unset($node);

        return $nodes;
    };

    return $build(0);
}

/**
 * Категория + все её потомки (родительская включает Подкатегории) —
 * произвольная глубина, не только один уровень.
 *
 * @param array<int, array{id: int, parent_id: ?int}> $categories
 * @return array<int, int>
 */
function catalogDescendantCategoryIds(array $categories, int $categoryId): array
{
    $childrenByParent = [];
    foreach ($categories as $category) {
        if ($category['parent_id'] !== null) {
            $childrenByParent[(int) $category['parent_id']][] = (int) $category['id'];
        }
    }

    $ids = [$categoryId];
    $queue = [$categoryId];
    while ($queue !== []) {
        $current = array_shift($queue);
        foreach ($childrenByParent[$current] ?? [] as $childId) {
            $ids[] = $childId;
            $queue[] = $childId;
        }
    }

    return $ids;
}

/**
 * Цепочка от корневой Категории до данной (для хлебных крошек) —
 * собирается по parent_id, без повторного запроса к БД.
 *
 * @param array<int, array{id: int, parent_id: ?int}> $categories
 * @return array<int, array<string, mixed>>
 */
function catalogCategoryChain(array $categories, int $categoryId): array
{
    $byId = [];
    foreach ($categories as $category) {
        $byId[(int) $category['id']] = $category;
    }

    $chain = [];
    $currentId = $categoryId;
    while ($currentId !== null && isset($byId[$currentId])) {
        array_unshift($chain, $byId[$currentId]);
        $currentId = $byId[$currentId]['parent_id'] !== null ? (int) $byId[$currentId]['parent_id'] : null;
    }

    return $chain;
}

/**
 * Счётчик Товаров на узел дерева Категорий для сайдбара — родительская
 * Категория включает Товары всех своих Подкатегорий, то же правило,
 * что и у листинга (catalogDescendantCategoryIds()).
 *
 * @param array<int, array{id: int, parent_id: ?int}> $categories
 * @param array<int, int>                              $directCounts Категория id => число Товаров именно в ней (productCountsByCategory())
 * @return array<int, int> Категория id => число Товаров с учётом Подкатегорий
 */
function catalogAggregateCategoryCounts(array $categories, array $directCounts): array
{
    $counts = [];
    foreach ($categories as $category) {
        $id = (int) $category['id'];
        $counts[$id] = array_sum(array_map(
            static fn (int $descendantId): int => $directCounts[$descendantId] ?? 0,
            catalogDescendantCategoryIds($categories, $id)
        ));
    }

    return $counts;
}

/**
 * URL текущей ветки каталога по цепочке slug'ов Категорий:
 * ['korma', 'suhoy-korm'] → '/catalog/korma/suhoy-korm', [] → '/catalog'.
 * Общий источник для canonical-ссылки (catalog.php) и action формы
 * сортировки/пагинации (components/catalog-results.php).
 *
 * @param array<int, string> $slugChain
 */
function catalogCanonicalPath(array $slugChain): string
{
    return $slugChain === [] ? '/catalog' : '/catalog/' . implode('/', $slugChain);
}

/**
 * Накопленные URL хлебных крошек по цепочке slug'ов Категорий:
 * ['korma', 'suhoy-korm'] → ['/catalog/korma', '/catalog/korma/suhoy-korm'].
 *
 * @param array<int, string> $slugChain
 * @return array<int, string>
 */
function catalogBreadcrumbUrls(array $slugChain): array
{
    $urls = [];
    $path = '/catalog';
    foreach ($slugChain as $slug) {
        $path .= '/' . $slug;
        $urls[] = $path;
    }

    return $urls;
}

// Характеристики, реально показанные в сайдбаре фильтра — остальные
// (вид_животного, Материал, Особенность, Размер, Цвет) убраны по
// просьбе пользователя: слишком длинный сайдбар чекбоксов, эти
// характеристики для фильтра избыточны. «Объём/размер» тоже убран
// (доп. правка) — эвристика сида (Таск 1) свалила в одну характеристику
// вес/объём/линейные размеры/количество разных Товаров по формальному
// признаку «единица измерения в скобках», а не по смыслу (44 значения:
// «1кг», «10л», «40x40см», «100шт», «без запаха», «палочка» — не общая
// ось фильтрации). Отбор здесь ограничивает и то, что принимает
// catalogNormalizeAttrFilter() (через $knownAttributes, см.
// productAttributeFacets()) — не только то, что показано в сайдбаре.
const CATALOG_FILTERABLE_ATTRIBUTES = ['Вкус'];

// Значения-шум внутри разрешённой Характеристики — та же эвристика сида
// (Таск 1): для части Товаров без явного вкуса (напр. добавки/таблетки)
// в attr_value попала дозировка/фасовка, а не вкус (доп. правка по
// скриншоту пользователя). Остальные значения «Вкус» (говядина, курица,
// лосось и т.д.) — настоящие вкусы, отбрасывать всю Характеристику
// незачем — убираем по конкретному значению.
const CATALOG_ATTRIBUTE_VALUE_DENYLIST = [
    'Вкус' => ['100г тюбик', '40г таблетки'],
];

/**
 * Сужает список Характеристик из БД (productAttributeFacets()) до тех,
 * что показываются в сайдбаре фильтра (CATALOG_FILTERABLE_ATTRIBUTES) —
 * порядок и состав сайдбара, а не всё, что в принципе есть в БД. Внутри
 * оставшихся Характеристик дополнительно убирает значения-шум
 * (CATALOG_ATTRIBUTE_VALUE_DENYLIST).
 *
 * @param array<string, array<int, string>> $facets
 * @return array<string, array<int, string>>
 */
function catalogFilterableAttributeFacets(array $facets): array
{
    $result = [];
    foreach (CATALOG_FILTERABLE_ATTRIBUTES as $attrName) {
        if (!isset($facets[$attrName])) {
            continue;
        }

        $denylist = CATALOG_ATTRIBUTE_VALUE_DENYLIST[$attrName] ?? [];
        $values = $denylist === []
            ? $facets[$attrName]
            : array_values(array_diff($facets[$attrName], $denylist));

        if ($values !== []) {
            $result[$attrName] = $values;
        }
    }

    return $result;
}

/**
 * Разбор Характеристик фильтра каталога/поиска (FR-CAT-002) из
 * $_GET['attr'] (attr_name => значение|список значений). Оставляет
 * только attr_name и значения, реально существующие в БД
 * ($knownAttributes — productAttributeFacets()) — против инъекций и
 * мусорных query-параметров.
 *
 * @param mixed $raw
 * @param array<string, array<int, string>> $knownAttributes attr_name => допустимые значения
 * @return array<string, array<int, string>>
 */
function catalogNormalizeAttrFilter(mixed $raw, array $knownAttributes): array
{
    if (!is_array($raw)) {
        return [];
    }

    $result = [];
    foreach ($raw as $attrName => $values) {
        if (!is_string($attrName) || !isset($knownAttributes[$attrName])) {
            continue;
        }

        $values = is_array($values) ? $values : [$values];
        $values = array_values(array_filter($values, 'is_string'));
        $values = array_values(array_intersect($values, $knownAttributes[$attrName]));

        if ($values !== []) {
            $result[$attrName] = $values;
        }
    }

    return $result;
}

/**
 * Разбор Бренда фильтра из $_GET['brand'] — только slug'и, реально
 * существующие среди активных Товаров ($knownSlugs).
 *
 * @param mixed $raw
 * @param array<int, string> $knownSlugs
 * @return array<int, string>
 */
function catalogNormalizeBrandFilter(mixed $raw, array $knownSlugs): array
{
    if ($raw === null) {
        return [];
    }

    $values = is_array($raw) ? $raw : [$raw];
    $values = array_values(array_filter($values, 'is_string'));

    return array_values(array_intersect($values, $knownSlugs));
}

/**
 * Граница диапазона цены (`price_min`/`price_max`, FR-CAT-003) —
 * неотрицательное число, иначе граница отсутствует (фильтр не
 * применяется, а не падает с ошибкой на мусорном вводе).
 */
function catalogNormalizePriceBound(mixed $raw): ?float
{
    if (is_int($raw) || is_float($raw)) {
        return $raw >= 0 ? (float) $raw : null;
    }

    if (is_string($raw) && is_numeric($raw)) {
        $value = (float) $raw;
        return $value >= 0 ? $value : null;
    }

    return null;
}

/**
 * Нормализация поискового запроса (FR-SRCH-001): обрезка пробелов и
 * длины (200 символов — как products.name); короче 2 символов
 * (включая запись из не-строки) — поиск не выполняется, возвращается
 * пустая строка.
 */
function catalogNormalizeSearchQuery(mixed $raw): string
{
    if (!is_string($raw)) {
        return '';
    }

    $query = mb_substr(trim($raw), 0, 200);

    return mb_strlen($query) >= 2 ? $query : '';
}

/**
 * Текущее состояние фильтра/поиска как query-параметры — общий
 * источник для ссылок пагинации, скрытых полей формы сортировки
 * (сохраняются при переключении сортировки/страницы, FR-CAT-005) и
 * fetch() в public/assets/js/catalog.js. Пустые/неактивные значения не
 * попадают в результат — иначе в URL появлялись бы пустые
 * `?price_min=&price_max=`.
 *
 * @param array{
 *     attr?: array<string, array<int, string>>,
 *     brand?: array<int, string>,
 *     price_min?: float|null,
 *     price_max?: float|null,
 *     q?: string
 * } $filter
 * @return array<string, mixed>
 */
function catalogFilterQueryParams(array $filter): array
{
    $query = [];

    if (($filter['attr'] ?? []) !== []) {
        $query['attr'] = $filter['attr'];
    }
    if (($filter['brand'] ?? []) !== []) {
        $query['brand'] = $filter['brand'];
    }
    if (($filter['price_min'] ?? null) !== null) {
        $query['price_min'] = $filter['price_min'];
    }
    if (($filter['price_max'] ?? null) !== null) {
        $query['price_max'] = $filter['price_max'];
    }
    if (($filter['q'] ?? '') !== '') {
        $query['q'] = $filter['q'];
    }

    return $query;
}

/**
 * Характеристики Карточки Товара для таблицы «пара свойство-значение»
 * (seo.md, Content rendering): бренд, подтверждённые Характеристики
 * Товара (product_attributes) и измерения Вариантов (размер, вкус...) —
 * только реальные данные из БД, ничего не придумывается.
 *
 * @param array<string, string> $attributes attr_name => значение (productConfirmedAttributes())
 * @param array<string, array<int, string>> $variantDimensions attr_name => значения Вариантов (productVariantDimensions())
 * @return array<string, string> подпись => значение, в порядке вывода
 */
function catalogProductSpecs(?string $brandName, array $attributes, array $variantDimensions): array
{
    $specs = [];

    if ($brandName !== null && trim($brandName) !== '') {
        $specs['Бренд'] = trim($brandName);
    }

    foreach ($attributes as $name => $value) {
        if (trim($value) !== '') {
            $specs[catalogSpecLabel($name)] = trim($value);
        }
    }

    foreach ($variantDimensions as $name => $values) {
        $values = array_values(array_unique(array_filter(array_map('trim', $values), static fn (string $v): bool => $v !== '')));
        if ($values !== []) {
            $specs[catalogSpecLabel($name)] = implode(', ', $values);
        }
    }

    return $specs;
}

/**
 * attr_name из БД (`вид_животного`) → подпись («Вид животного»).
 */
function catalogSpecLabel(string $attrName): string
{
    $label = trim(str_replace('_', ' ', $attrName));

    return mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1);
}
