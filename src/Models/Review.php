<?php

declare(strict_types=1);

/**
 * Модель Отзывов — только SQL через PDO, возвращает массивы (php.md).
 * Чтение для блока на Главной (FR-HOME-008) — Таск 5; создание отзыва
 * Гостем/Покупателем и модерация (`status`, `moderated_by_user_id`) —
 * Таск 8 (`FR-CARD-005`/`Q-048`, `ADR-011`).
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

/**
 * Опубликованные отзывы конкретного Товара — Карточка товара
 * (`FR-CARD-005`), самые новые сверху.
 *
 * @return array<int, array<string, mixed>>
 */
function reviewsPublishedForProduct(int $productId): array
{
    $stmt = getPdo()->prepare("
        SELECT author_name, rating, body, created_at
        FROM reviews
        WHERE product_id = ? AND status = 'published'
        ORDER BY created_at DESC
    ");
    $stmt->execute([$productId]);

    return $stmt->fetchAll();
}

/**
 * Новый отзыв из формы `add-review` — всегда `pending` (`Q-048`), не
 * публикуется без модерации. `$userId` — NULL, если оставлен Гостем.
 */
function reviewCreate(
    int $productId,
    ?int $userId,
    string $authorName,
    string $authorEmail,
    int $rating,
    string $body
): void {
    $stmt = getPdo()->prepare("
        INSERT INTO reviews (product_id, user_id, author_name, author_email, rating, body, status)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$productId, $userId, $authorName, $authorEmail, $rating, $body]);
}

/**
 * Очередь модерации `/admin/reviews` (`admin-assembly.md`) — с названием
 * Товара, самые старые первыми (кто ждёт дольше — обрабатывается раньше).
 *
 * @return array<int, array<string, mixed>>
 */
function reviewsPending(): array
{
    return getPdo()->query("
        SELECT r.id, r.author_name, r.rating, r.body, r.created_at, p.name AS product_name
        FROM reviews r
        JOIN products p ON p.id = r.product_id
        WHERE r.status = 'pending'
        ORDER BY r.created_at ASC
    ")->fetchAll();
}

/**
 * Публикация/отклонение отзыва — единственное место, меняющее `status`
 * (не прямой `UPDATE` из вида, `admin-assembly.md`). Условие
 * `status = 'pending'` в WHERE — повторный клик по уже обработанному
 * отзыву не перезатирает решение первого модератора.
 *
 * @return bool true, если строка была в очереди и переведена в новый статус
 */
function reviewModerate(int $id, string $status, int $moderatorUserId): bool
{
    if (!in_array($status, ['published', 'rejected'], true)) {
        throw new InvalidArgumentException("Недопустимый статус отзыва: {$status}");
    }

    $stmt = getPdo()->prepare("
        UPDATE reviews
        SET status = ?, moderated_by_user_id = ?, moderated_at = NOW()
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->execute([$status, $moderatorUserId, $id]);

    return $stmt->rowCount() > 0;
}
