<?php

declare(strict_types=1);

/**
 * Модель статических страниц (`content_pages`, `content_page_images`,
 * phase-8.md, Таск 1). Только SQL через PDO, возвращает массивы (php.md).
 */

/**
 * Страница по slug; null — такой страницы нет.
 *
 * @return array<string, mixed>|null
 */
function contentPageFindBySlug(string $slug): ?array
{
    $stmt = getPdo()->prepare('
        SELECT id, slug, title, body, seo_title, seo_description, updated_at
        FROM content_pages
        WHERE slug = :slug
        LIMIT 1
    ');
    $stmt->execute(['slug' => $slug]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Изображения страницы в порядке показа (`path` — относительно public/uploads/).
 *
 * @return array<int, array{id: int, path: string, sort_order: int}>
 */
function contentPageImages(int $contentPageId): array
{
    $stmt = getPdo()->prepare('
        SELECT id, path, sort_order
        FROM content_page_images
        WHERE content_page_id = :id
        ORDER BY sort_order, id
    ');
    $stmt->execute(['id' => $contentPageId]);

    return $stmt->fetchAll();
}
