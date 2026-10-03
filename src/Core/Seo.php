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
        'service' => sprintf('%s в %s, %s', (string) ($entity['name'] ?? 'Услуги'), SHOP_NAME, SHOP_CITY),
        'services' => sprintf('Услуги: груминг и ветконсультации — %s, %s', SHOP_NAME, SHOP_CITY),
        'search' => ((string) ($entity['query'] ?? '')) !== ''
            ? sprintf('Поиск: «%s» — %s', (string) $entity['query'], SHOP_NAME)
            : sprintf('Поиск товаров — %s', SHOP_NAME),
        'login' => sprintf('Вход в личный кабинет — %s', SHOP_NAME),
        'forgot-password' => sprintf('Восстановление пароля — %s', SHOP_NAME),
        'cart' => sprintf('Корзина — %s', SHOP_NAME),
        'checkout' => sprintf('Оформление заказа — %s', SHOP_NAME),
        'booking' => sprintf('Запись на груминг и ветконсультацию — %s', SHOP_NAME),
        'booking-success' => sprintf('Запись создана — %s', SHOP_NAME),
        'account-bookings' => sprintf('Мои записи — %s', SHOP_NAME),
        'account-orders' => sprintf('Мои заказы — %s', SHOP_NAME),
        'account-order' => sprintf('Заказ №%d — %s', (int) ($entity['id'] ?? 0), SHOP_NAME),
        'account-returns' => sprintf('Возвраты — %s', SHOP_NAME),
        'account-return-form' => sprintf('Заявка на возврат — %s', SHOP_NAME),
        'order-success' => sprintf('Заказ оформлен — %s', SHOP_NAME),
        'payment' => sprintf('Оплата заказа — %s', SHOP_NAME),
        'payment-failed' => sprintf('Оплата не прошла — %s', SHOP_NAME),
        'booking-payment' => sprintf('Оплата депозита за запись — %s', SHOP_NAME),
        'booking-payment-failed' => sprintf('Оплата депозита не прошла — %s', SHOP_NAME),
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
        'service' => seoFirstWords((string) ($entity['description'] ?? ''), 25) !== ''
            ? seoFirstWords((string) $entity['description'], 25)
            : sprintf('%s — запись онлайн в %s, %s.', (string) ($entity['name'] ?? 'Услуга'), SHOP_NAME, SHOP_CITY),
        'services' => sprintf('Услуги %s, %s: груминг и ветеринарные консультации, цены и онлайн-запись.', SHOP_NAME, SHOP_CITY),
        'search' => ((string) ($entity['query'] ?? '')) !== ''
            ? sprintf('Результаты поиска «%s» в каталоге %s, %s.', (string) $entity['query'], SHOP_NAME, SHOP_CITY)
            : sprintf('Поиск товаров в каталоге %s, %s.', SHOP_NAME, SHOP_CITY),
        'login' => sprintf('Вход в личный кабинет покупателя %s, %s.', SHOP_NAME, SHOP_CITY),
        'forgot-password' => sprintf('Восстановление пароля личного кабинета %s.', SHOP_NAME),
        'cart' => sprintf('Корзина покупок %s, %s: товары для кошек, собак и птиц.', SHOP_NAME, SHOP_CITY),
        'checkout' => sprintf('Оформление заказа в %s, %s: контакты, доставка и оплата.', SHOP_NAME, SHOP_CITY),
        'booking' => sprintf('Онлайн-запись на груминг и ветконсультацию в %s, %s: выберите услугу, специалиста и время.', SHOP_NAME, SHOP_CITY),
        'booking-success' => sprintf('Запись на услуги в %s, %s, создана.', SHOP_NAME, SHOP_CITY),
        'account-bookings' => sprintf('Ближайшие записи на груминг и ветконсультацию в %s, %s, и их отмена.', SHOP_NAME, SHOP_CITY),
        'account-orders' => sprintf('История ваших заказов в %s, %s: статусы и суммы.', SHOP_NAME, SHOP_CITY),
        'account-order' => sprintf('Состав, доставка и оплата заказа №%d в %s, %s.', (int) ($entity['id'] ?? 0), SHOP_NAME, SHOP_CITY),
        'account-returns' => sprintf('Заказы, доступные для возврата, и ваши заявки на возврат в %s, %s.', SHOP_NAME, SHOP_CITY),
        'account-return-form' => sprintf('Заявка на возврат заказа в %s, %s: причина и фото.', SHOP_NAME, SHOP_CITY),
        'order-success' => sprintf('Заказ успешно оформлен в %s, %s.', SHOP_NAME, SHOP_CITY),
        'payment' => sprintf('Оплата заказа картой в %s, %s.', SHOP_NAME, SHOP_CITY),
        'payment-failed' => sprintf('Оплата заказа в %s, %s, не прошла: повторите попытку или отмените заказ.', SHOP_NAME, SHOP_CITY),
        'booking-payment' => sprintf('Оплата депозита за запись на услуги в %s, %s.', SHOP_NAME, SHOP_CITY),
        'booking-payment-failed' => sprintf('Оплата депозита за запись в %s, %s, не прошла: повторите попытку или отмените запись.', SHOP_NAME, SHOP_CITY),
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

/**
 * JSON-LD Product (FR-CARD, dod-global.md «SEO и GEO») — цена и
 * availability передаются уже посчитанными во View (тот же
 * $effectivePrice/$status, что рисует видимую цену/наличие), а не
 * пересчитываются здесь заново из сырых данных Варианта — иначе
 * возможно разойдётся с тем, что видит покупатель.
 *
 * @param array{name: string, description: ?string, sku: string} $entity
 * @param string $availabilityStatus 'in'|'low'|'out' — catalogAvailabilityStatus()
 * @param array<int, string> $imageUrls Абсолютные URL фото Товара
 */
function renderProductSchema(
    array $entity,
    float $price,
    string $availabilityStatus,
    string $productUrl,
    array $imageUrls = []
): string {
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $entity['name'],
        'sku' => $entity['sku'],
        'url' => APP_URL . $productUrl,
        'offers' => [
            '@type' => 'Offer',
            'url' => APP_URL . $productUrl,
            'priceCurrency' => 'RUB',
            'price' => number_format($price, 2, '.', ''),
            'availability' => $availabilityStatus === 'out'
                ? 'https://schema.org/OutOfStock'
                : 'https://schema.org/InStock',
        ],
    ];

    if (($entity['description'] ?? '') !== '') {
        $schema['description'] = $entity['description'];
    }
    if ($imageUrls !== []) {
        $schema['image'] = $imageUrls;
    }

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
