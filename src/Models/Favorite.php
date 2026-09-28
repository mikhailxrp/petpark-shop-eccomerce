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
