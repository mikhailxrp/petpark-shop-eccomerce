<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Кнопка «В избранное» на Карточке товара — `FR-CARD-006`, phase-1.md
 * Таск 8. `requireRole('customer')` сам уводит Гостя на `/login`
 * («предложение войти»), персонал — на свою сводку; форма кнопки одна
 * и та же для всех, ветвления по роли во View нет.
 */
final class FavoriteController
{
    public function toggle(): void
    {
        requireCsrf();
        requireRole('customer');

        $slug = trim((string) input('slug'));
        $variantId = (int) input('variant_id');
        $userId = (int) $_SESSION['user_id'];

        $product = $slug !== '' ? productFindBySlug($slug) : null;
        $redirectUrl = $product !== null ? '/product/' . $slug . '/?variant=' . $variantId : '/catalog';

        $result = $variantId > 0 ? favoriteToggle($userId, $variantId) : null;

        setFlash(
            'favorite_notice',
            match ($result) {
                true => 'Добавлено в избранное.',
                false => 'Убрано из избранного.',
                null => 'Не удалось изменить избранное — товар недоступен.',
            }
        );

        redirect($redirectUrl);
    }
}
