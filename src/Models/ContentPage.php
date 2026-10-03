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

/**
 * Slug и дата правки всех страниц — для sitemap.xml.
 *
 * @return array<int, array{slug: string, updated_at: string}>
 */
function contentPageListForSitemap(): array
{
    return getPdo()->query('
        SELECT slug, updated_at
        FROM content_pages
    ')->fetchAll();
}

/**
 * Все страницы для списка в админке.
 *
 * @return array<int, array{id: int, slug: string, title: string, updated_at: string}>
 */
function contentPageList(): array
{
    return getPdo()->query('
        SELECT id, slug, title, updated_at
        FROM content_pages
        ORDER BY id
    ')->fetchAll();
}

/**
 * Страница по id; null — такой страницы нет.
 *
 * @return array<string, mixed>|null
 */
function contentPageFindById(int $id): ?array
{
    $stmt = getPdo()->prepare('
        SELECT id, slug, title, body, seo_title, seo_description, updated_at
        FROM content_pages
        WHERE id = :id
        LIMIT 1
    ');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function contentPageUpdate(int $id, string $title, string $body, ?string $seoTitle, ?string $seoDescription): void
{
    $stmt = getPdo()->prepare('
        UPDATE content_pages
        SET title = :title, body = :body, seo_title = :seo_title, seo_description = :seo_description
        WHERE id = :id
    ');
    $stmt->execute([
        'id'              => $id,
        'title'           => $title,
        'body'            => $body,
        'seo_title'       => $seoTitle,
        'seo_description' => $seoDescription,
    ]);
}

function contentPageImageCount(int $contentPageId): int
{
    $stmt = getPdo()->prepare('SELECT COUNT(*) FROM content_page_images WHERE content_page_id = :id');
    $stmt->execute(['id' => $contentPageId]);

    return (int) $stmt->fetchColumn();
}

/** Новое изображение встаёт в конец галереи. */
function contentPageImageAdd(int $contentPageId, string $path): void
{
    $stmt = getPdo()->prepare('
        INSERT INTO content_page_images (content_page_id, path, sort_order)
        SELECT :id, :path, COALESCE(MAX(sort_order) + 1, 0)
        FROM content_page_images
        WHERE content_page_id = :same_id
    ');
    $stmt->execute(['id' => $contentPageId, 'same_id' => $contentPageId, 'path' => $path]);
}

/**
 * Изображение именно этой страницы — чужой id картинки даёт null.
 *
 * @return array{id: int, path: string, sort_order: int}|null
 */
function contentPageImageFind(int $imageId, int $contentPageId): ?array
{
    $stmt = getPdo()->prepare('
        SELECT id, path, sort_order
        FROM content_page_images
        WHERE id = :id AND content_page_id = :page_id
        LIMIT 1
    ');
    $stmt->execute(['id' => $imageId, 'page_id' => $contentPageId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function contentPageImageDelete(int $imageId, int $contentPageId): void
{
    $stmt = getPdo()->prepare('DELETE FROM content_page_images WHERE id = :id AND content_page_id = :page_id');
    $stmt->execute(['id' => $imageId, 'page_id' => $contentPageId]);
}
