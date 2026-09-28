<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * /sitemap.xml (phase-1.md, Таск 9, [INFRA] из _status.md) — реальные
 * URL активных Товаров и всех Категорий (`categories` без признака
 * активности — все включены, database.md). Результат кешируется файлом
 * (Core/Cache.php) — не полный скан products/categories на каждый
 * заход краулера.
 */
final class SitemapController
{
    private const CACHE_TTL_SECONDS = 3600;
    private const CACHE_KEY = 'sitemap.xml';

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
        $urls = [];

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
