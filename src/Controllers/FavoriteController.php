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

        $result = $variantId > 0 ? favoriteToggle($userId, $variantId) : null;

        // Из листинга (карточка каталога/главной) возвращаем туда же, где нажали, — с фильтрами и страницей;
        // flash-сообщение показывает только Карточка товара, в листинге о результате говорит сердечко.
        $returnUrl = $this->localReturnUrl((string) input('return'));
        if ($returnUrl !== null) {
            redirect($returnUrl);
        }

        $product = $slug !== '' ? productFindBySlug($slug) : null;
        $redirectUrl = $product !== null ? '/product/' . $slug . '/?variant=' . $variantId : '/catalog';

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

    /**
     * Только путь своего сайта: начинается с одного `/`, без `//`, `\` и
     * управляющих символов — иначе `return` стал бы open redirect.
     */
    private function localReturnUrl(string $raw): ?string
    {
        $isLocalPath = $raw !== '' && $raw[0] === '/'
            && !str_starts_with($raw, '//')
            && !str_contains($raw, '\\')
            && preg_match('/[\x00-\x1F\x7F]/', $raw) !== 1;

        return $isLocalPath ? $raw : null;
    }
}
