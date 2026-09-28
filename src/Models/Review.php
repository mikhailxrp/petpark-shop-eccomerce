<?php

declare(strict_types=1);

/**
 * Модель Отзывов — только SQL через PDO, возвращает массивы (php.md).
 * Модерация (`status`, `moderated_by_user_id`) и форма `add-review` на
 * Карточке товара — Таск 8; здесь только чтение опубликованных отзывов
 * для блока на Главной (FR-HOME-008).
 */

/**
 * Опубликованные отзывы для блока на Главной — с именем/slug Товара,
 * на который оставлен отзыв.
 *
 * @return array<int, array<string, mixed>>
 */
function reviewPublishedForHome(int $limit): array
{
    $limit = (int) $limit;

    $stmt = getPdo()->query("
        SELECT
            r.id, r.author_name, r.rating, r.body, r.created_at,
            p.name AS product_name, p.slug AS product_slug
        FROM reviews r
        JOIN products p ON p.id = r.product_id
        WHERE r.status = 'published'
        ORDER BY r.created_at DESC
        LIMIT {$limit}
    ");

    return $stmt->fetchAll();
}

/**
 * Средний рейтинг и число опубликованных отзывов — бейдж «Рейтинг X из
 * 5.0» под блоком отзывов на Главной. Считаем по ВСЕМ опубликованным
 * отзывам (не только по тем `$limit`, что выводятся каруселью) —
 * реальное среднее, а не по показанной выборке.
 *
 * @return array{average: float, count: int}
 */
function reviewPublishedAggregate(): array
{
    $row = getPdo()->query("
        SELECT COUNT(*) AS reviews_count, AVG(rating) AS average_rating
        FROM reviews
        WHERE status = 'published'
    ")->fetch();

    return [
        'average' => $row['average_rating'] !== null ? round((float) $row['average_rating'], 1) : 0.0,
        'count'   => (int) $row['reviews_count'],
    ];
}
