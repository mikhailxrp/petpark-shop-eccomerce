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
