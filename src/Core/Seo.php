<?php

declare(strict_types=1);

/**
 * Генератор <title>/<meta description> — правило генерации SEO-полей
 * (.docs/database.md) и шаблоны PetPark (ADR-004).
 * Дефолты SHOP_NAME/SHOP_CITY ниже — только для контекста без config.php
 * (юнит-тесты); в приложении их переопределяет config.php из .env.
 */

if (!defined('SHOP_NAME')) {
    define('SHOP_NAME', 'PetPark');
}

if (!defined('SHOP_CITY')) {
    define('SHOP_CITY', 'Ростов-на-Дону');
}

if (!defined('APP_URL')) {
    define('APP_URL', '');
}

function seoTitle(string $type, array $entity = []): string
{
    $filled = trim((string) ($entity['seo_title'] ?? ''));
    if ($filled !== '') {
        return $filled;
    }

    return match ($type) {
        'product' => sprintf(
            '%s — купить в %s, цена %s ₽',
            (string) $entity['name'],
            SHOP_NAME,
            seoFormatPrice($entity['price'] ?? 0)
        ),
        'category' => sprintf('%s — купить в %s, %s', (string) $entity['name'], SHOP_NAME, SHOP_CITY),
        'content_page' => (string) ($entity['title'] ?? SHOP_NAME),
        'search' => ((string) ($entity['query'] ?? '')) !== ''
            ? sprintf('Поиск: «%s» — %s', (string) $entity['query'], SHOP_NAME)
            : sprintf('Поиск товаров — %s', SHOP_NAME),
        default => sprintf('%s — зоомагазин и центр ухода за питомцами, %s', SHOP_NAME, SHOP_CITY),
    };
}

function seoDescription(string $type, array $entity = []): string
{
    $filled = trim((string) ($entity['seo_description'] ?? ''));
    if ($filled !== '') {
        return $filled;
    }

    return match ($type) {
        'product' => sprintf(
            'Купить %s с доставкой и самовывозом — %s, %s.',
            (string) $entity['name'],
            SHOP_NAME,
            SHOP_CITY
        ),
        'category' => sprintf(
            'Каталог «%s» — %s, %s: доставка и самовывоз.',
            (string) $entity['name'],
            SHOP_NAME,
            SHOP_CITY
        ),
        'content_page' => seoFirstWords((string) ($entity['body'] ?? ''), 25),
        'search' => ((string) ($entity['query'] ?? '')) !== ''
            ? sprintf('Результаты поиска «%s» в каталоге %s, %s.', (string) $entity['query'], SHOP_NAME, SHOP_CITY)
            : sprintf('Поиск товаров в каталоге %s, %s.', SHOP_NAME, SHOP_CITY),
        default => sprintf(
            '%s — доставка и самовывоз, груминг и ветконсультации, %s.',
            SHOP_NAME,
            SHOP_CITY
        ),
    };
}

function seoFormatPrice(int|float|string $price): string
{
    return (string) (int) round((float) $price);
}

/**
 * JSON-LD BreadcrumbList (FR-CAT-006) — из той же цепочки, что видимые
 * хлебные крошки, не отдельный источник (dod-global.md, «SEO и GEO»).
 *
 * @param array<int, array{name: string, url: ?string}> $items Главная → ... → текущая страница
 */
function renderBreadcrumbSchema(array $items): string
{
    $itemListElement = [];
    foreach (array_values($items) as $position => $item) {
        $listItem = [
            '@type' => 'ListItem',
            'position' => $position + 1,
            'name' => $item['name'],
        ];
        if (!empty($item['url'])) {
            $listItem['item'] = APP_URL . $item['url'];
        }
        $itemListElement[] = $listItem;
    }

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $itemListElement,
    ];

    return '<script type="application/ld+json">'
        . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . '</script>';
}

function seoFirstWords(string $text, int $wordCount): string
{
    $text = trim($text);
    if ($text === '') {
        return sprintf('%s — %s', SHOP_NAME, SHOP_CITY);
    }

    $words = preg_split('/\s+/u', $text) ?: [];
    if (count($words) <= $wordCount) {
        return $text;
    }

    return implode(' ', array_slice($words, 0, $wordCount)) . '…';
}
