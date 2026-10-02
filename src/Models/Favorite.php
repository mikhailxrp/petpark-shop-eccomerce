<?php

declare(strict_types=1);

/**
 * Модель Избранного — только SQL через PDO, возвращает массивы (php.md).
 * `FR-CARD-006` (кнопка «В избранное» на Карточке товара, Таск 8),
 * `database.md` (`ADR-012`).
 */

/**
 * Стоит ли Вариант в избранном у Покупателя — для отрисовки состояния
 * кнопки (`fa-solid`/`fa-regular fa-heart`) на Карточке товара.
 */
function favoriteExists(int $userId, int $variantId): bool
{
    $stmt = getPdo()->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND variant_id = ?');
    $stmt->execute([$userId, $variantId]);

    return $stmt->fetchColumn() !== false;
}

/**
 * Добавляет/убирает Вариант из избранного Покупателя. INSERT идёт через
 * `SELECT ... WHERE is_active = 1` (не голый INSERT) — так же, как
 * проверка наличия Варианта при чтении каталога (`Product.php`), не
 * даёт добавить в избранное деактивированный/несуществующий Вариант.
 *
 * @return bool|null true — добавлено, false — убрано, null — Вариант не найден/неактивен
 */
function favoriteToggle(int $userId, int $variantId): ?bool
{
    $pdo = getPdo();

    $stmt = $pdo->prepare('SELECT id FROM favorites WHERE user_id = ? AND variant_id = ?');
    $stmt->execute([$userId, $variantId]);
    $existingId = $stmt->fetchColumn();

    if ($existingId !== false) {
        $pdo->prepare('DELETE FROM favorites WHERE id = ?')->execute([(int) $existingId]);
        return false;
    }

    $stmt = $pdo->prepare('
        INSERT INTO favorites (user_id, variant_id)
        SELECT ?, id FROM product_variants WHERE id = ? AND is_active = 1
    ');
    $stmt->execute([$userId, $variantId]);

    return $stmt->rowCount() > 0 ? true : null;
}

/**
 * Избранное Покупателя для `/account/favorites` (`FR-ACC-004`): только
 * активные Варианты активных Товаров, свежие сверху. Подпись Варианта —
 * один подзапрос (характеристики, иначе артикул), как в
 * `productSearchVariants()`. Наличие отдаётся сырыми числами — статус
 * считает Controller (`catalogAvailabilityStatus()`).
 *
 * @return array<int, array<string, mixed>>
 */
function favoritesByUser(int $userId): array
{
    $stmt = getPdo()->prepare('
        SELECT v.id AS variant_id, v.sku, v.price, v.discount_price,
               v.stock_quantity, v.reserved_quantity,
               p.name, p.slug,
               (
                   SELECT GROUP_CONCAT(a.attr_value ORDER BY a.attr_name SEPARATOR \', \')
                   FROM product_variant_attributes a
                   WHERE a.variant_id = v.id
               ) AS attributes_label
        FROM favorites f
        JOIN product_variants v ON v.id = f.variant_id AND v.is_active = 1
        JOIN products p ON p.id = v.product_id AND p.is_active = 1
        WHERE f.user_id = ?
        ORDER BY f.created_at DESC, f.id DESC
    ');
    $stmt->execute([$userId]);

    return $stmt->fetchAll();
}

/**
 * Убирает Вариант из избранного Покупателя. Условие по `user_id` — чужую
 * запись по подставленному id не удалить (dod-global.md).
 *
 * @return bool false — такой записи у Покупателя нет
 */
function favoriteRemove(int $userId, int $variantId): bool
{
    $stmt = getPdo()->prepare('DELETE FROM favorites WHERE user_id = ? AND variant_id = ?');
    $stmt->execute([$userId, $variantId]);

    return $stmt->rowCount() > 0;
}

/**
 * Id избранных Вариантов текущего Покупателя — для закрашенного сердечка на
 * карточках листингов (components/product-card.php). Гость и персонал —
 * пустой список. Читает сессию здесь, а не в View: View не ходит в БД.
 *
 * @return array<int, int>
 */
function favoriteVariantIdsForCurrentCustomer(): array
{
    if (!isAuthenticated() || ($_SESSION['user_role'] ?? null) !== 'customer') {
        return [];
    }

    $stmt = getPdo()->prepare('SELECT variant_id FROM favorites WHERE user_id = ?');
    $stmt->execute([(int) $_SESSION['user_id']]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}
