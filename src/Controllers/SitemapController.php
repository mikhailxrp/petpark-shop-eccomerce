<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * /sitemap.xml (phase-1.md, Таск 9, [INFRA] из _status.md) — реальные
 * URL активных Товаров, всех Категорий (`categories` без признака
 * активности — все включены, database.md), страниц фазы 8 (главная,
 * статические страницы из `content_pages`, Услуги, Тарифы, профили
 * Специалистов; неактивные Услуги и отключённые сотрудники не попадают —
 * phase-8.md, Таск 8). Результат кешируется файлом
 * (Core/Cache.php) — не полный скан products/categories на каждый
 * заход краулера.
 */
final class SitemapController
{
    private const CACHE_TTL_SECONDS = 3600;
    private const CACHE_KEY = 'sitemap.xml';

    // Страницы `content_pages`, у которых есть публичный маршрут
    // (config/routes.php) — строка с другим slug дала бы 404 в sitemap.
    private const CONTENT_PAGE_SLUGS = ['about', 'contacts', 'privacy', 'offer', 'delivery', 'payment'];

    public function index(): void
    {
        $xml = cacheGet('sitemap', self::CACHE_KEY, self::CACHE_TTL_SECONDS);

        if ($xml === null) {
            $xml = $this->build();
            cachePut('sitemap', self::CACHE_KEY, $xml);
        }

        header('Content-Type: application/xml; charset=UTF-8');
        echo $xml;
    }

    private function build(): string
    {
        $urls = [
            ['loc' => '/', 'lastmod' => null],
            ['loc' => '/services', 'lastmod' => null],
            ['loc' => '/pricing', 'lastmod' => null],
        ];

        foreach (contentPageListForSitemap() as $page) {
            if (in_array($page['slug'], self::CONTENT_PAGE_SLUGS, true)) {
                $urls[] = ['loc' => '/' . $page['slug'], 'lastmod' => $page['updated_at']];
            }
        }

        foreach (servicesPublicList() as $service) {
            $urls[] = ['loc' => '/services/' . $service['slug'], 'lastmod' => null];
        }

        foreach (specialistListForPublic() as $specialist) {
            $urls[] = ['loc' => '/team/' . $specialist['slug'], 'lastmod' => null];
        }

        foreach (productActiveForSitemap() as $product) {
            $urls[] = [
                'loc' => '/product/' . $product['slug'] . '/',
                'lastmod' => $product['updated_at'],
            ];
        }

        $categories = categoryAll();
        foreach ($categories as $category) {
            $chain = catalogCategoryChain($categories, (int) $category['id']);
            $urls[] = ['loc' => catalogCanonicalPath(array_column($chain, 'slug')), 'lastmod' => null];
        }

        return $this->render($urls);
    }

    /**
     * @param array<int, array{loc: string, lastmod: ?string}> $urls
     */
    private function render(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $xml .= '  <url>' . "\n"
                . '    <loc>' . e(APP_URL . $url['loc']) . '</loc>' . "\n";
            if ($url['lastmod'] !== null) {
                $xml .= '    <lastmod>' . e(date('Y-m-d', strtotime($url['lastmod']))) . '</lastmod>' . "\n";
            }
            $xml .= '  </url>' . "\n";
        }

        return $xml . '</urlset>';
    }
}
