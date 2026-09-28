<?php

declare(strict_types=1);

/**
 * Чистые функции разбора исходных данных каталога (без БД и без файловой
 * системы) — вынесены отдельно от database/seed-catalog.php, чтобы их можно
 * было покрыть unit-тестами без HTTP/БД-контекста (php.md, «Тестирование»).
 * Покрыты tests/Unit/CatalogParserTest.php.
 */

const VARIANT_UNIT_PATTERN = '/\d+([.,]\d+)?\s*(г|кг|мл|л|см|мм|м|шт)\.?$/u';
const VARIANT_LETTER_SIZES = ['s', 'm', 'l', 'xl', 'xs'];
const VARIANT_COLORS = [
    'чёрный', 'синий', 'красный', 'серый', 'бежевый', 'розовый', 'голубой',
    'коричневый', 'жёлтый', 'зелёный', 'оранжевый', 'белый',
];
const VARIANT_MATERIALS = ['керамика', 'нержавейка', 'пластик', 'дерево', 'металл', 'резина', 'джут', 'хлопок'];
const VARIANT_FOOD_CATEGORIES = ['Корма', 'Лакомства'];
const VARIANT_SIZE_ATTR_NAMES = ['Объём/размер', 'Размер'];

/**
 * Транслитерация кириллицы в латиницу + нормализация в URL-slug.
 */
function slugify(string $text): string
{
    $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
        'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    $text = strtr(mb_strtolower($text), $map);
    $text = preg_replace('/[^a-z0-9]+/u', '-', $text) ?? '';

    return trim($text, '-');
}

/**
 * Определяет смысловое имя измерения варианта по его значениям:
 * вес/объём/размер (по единице измерения или буквенному размеру S/M/L),
 * цвет, материал, либо частный признак — вкус (для еды) / особенность.
 *
 * @param list<string> $values
 */
function classifyVariantDimension(array $values, string $category): string
{
    $first = mb_strtolower(trim($values[0]));

    if (preg_match(VARIANT_UNIT_PATTERN, $first) === 1 || preg_match('/^\d+x\d+/u', $first) === 1) {
        return 'Объём/размер';
    }

    $letterToken = preg_replace('/[^a-zа-я]/u', '', $first) ?? '';
    if (in_array($letterToken, VARIANT_LETTER_SIZES, true)) {
        return 'Размер';
    }

    foreach (VARIANT_COLORS as $color) {
        if (str_contains($first, $color)) {
            return 'Цвет';
        }
    }

    foreach (VARIANT_MATERIALS as $material) {
        if (str_contains($first, $material)) {
            return 'Материал';
        }
    }

    if (str_contains($first, 'запаха') || $first === 'универсально') {
        return 'Особенность';
    }

    return in_array($category, VARIANT_FOOD_CATEGORIES, true) ? 'Вкус' : 'Особенность';
}

/**
 * Разбирает строку колонки «Варианты (вес/вкус/размер)» на список измерений.
 * Сегменты разделены `;`, значения внутри сегмента — `/` либо `,`.
 * Хвостовое слово «размер»/«вес» относится ко всему сегменту, а не к
 * последнему значению — отбрасывается перед разбиением на значения.
 *
 * @return list<array{attr_name: string, values: list<string>}>
 */
function parseVariantDimensions(string $raw, string $category): array
{
    $segments = array_map('trim', explode(';', $raw));
    $dimensions = [];

    foreach ($segments as $segment) {
        if ($segment === '') {
            continue;
        }

        $segment = preg_replace('/\s+(размер|вес)$/iu', '', $segment) ?? $segment;

        $values = array_map('trim', explode('/', $segment));
        if (count($values) === 1 && str_contains($segment, ',')) {
            $values = array_map('trim', explode(',', $segment));
        }
        $values = array_values(array_filter($values, static fn (string $v): bool => $v !== ''));

        if ($values === []) {
            continue;
        }

        $dimensions[] = [
            'attr_name' => classifyVariantDimension($values, $category),
            'values'    => $values,
        ];
    }

    return $dimensions;
}

/**
 * Декартово произведение измерений варианта. Без измерений — один вариант
 * без атрибутов (товар с единственным SKU).
 *
 * @param list<array{attr_name: string, values: list<string>}> $dimensions
 * @return list<array<string, string>>
 */
function buildVariantCombinations(array $dimensions): array
{
    if ($dimensions === []) {
        return [[]];
    }

    $combinations = [[]];
    foreach ($dimensions as $dimension) {
        $next = [];
        foreach ($combinations as $combo) {
            foreach ($dimension['values'] as $value) {
                $next[] = $combo + [$dimension['attr_name'] => $value];
            }
        }
        $combinations = $next;
    }

    return $combinations;
}

/**
 * Множитель цены варианта относительно позиции значения в первом размерном
 * измерении (0 -> 1.0, 1 -> 1.5, 2 -> 2.0, ...). Вкус/цвет/материал на цену
 * не влияют — в реальном магазине упаковка большего объёма стоит дороже,
 * а вкус в рамках одного объёма — нет.
 *
 * @param array<string, string> $combo
 * @param list<array{attr_name: string, values: list<string>}> $dimensions
 */
function variantPriceMultiplier(array $combo, array $dimensions): float
{
    foreach ($dimensions as $dimension) {
        if (!in_array($dimension['attr_name'], VARIANT_SIZE_ATTR_NAMES, true)) {
            continue;
        }

        $index = array_search($combo[$dimension['attr_name']] ?? null, $dimension['values'], true);
        if ($index !== false) {
            return 1.0 + 0.5 * $index;
        }
    }

    return 1.0;
}

/**
 * Делит суммарный остаток товара между его вариантами: базовая часть —
 * целочисленное деление, остаток от деления раздаётся первым вариантам.
 *
 * @return list<int>
 */
function distributeStock(int $totalStock, int $variantCount): array
{
    if ($variantCount <= 0) {
        return [];
    }

    $base      = intdiv($totalStock, $variantCount);
    $remainder = $totalStock % $variantCount;

    $result = [];
    for ($i = 0; $i < $variantCount; $i++) {
        $result[] = $base + ($i < $remainder ? 1 : 0);
    }

    return $result;
}

function roundToNearestTen(float $value): float
{
    return round($value / 10) * 10;
}
