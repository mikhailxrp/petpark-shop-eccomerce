<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

/**
 * Модерация отзывов — /admin/reviews (phase-1.md, Таск 8). Доступна
 * только `shift_admin`/`owner` (`admin-assembly.md`: «Модерация
 * отзывов» — раздел CARD, не ADM/MGR, но то же ролевое ограничение,
 * что у остальной Админки) — `content_editor`/`specialist` не видят
 * пункт меню (`layouts/admin.php`) и получают отказ при прямом заходе.
 */
final class ReviewController
{
    public function index(): void
    {
        requireRole('shift_admin', 'owner');

        $role = (string) $_SESSION['user_role'];

        render('admin/reviews', [
            'pageTitle' => 'Модерация отзывов — PetPark',
            'roleLabel' => adminRoleLabel($role),
            'homeUrl'   => homePathForRole($role),
            'userRole'  => $role,
            'reviews'   => reviewsPending(),
            'success'   => getFlash('success'),
        ]);
    }

    public function publish(string $id): void
    {
        requireRole('shift_admin', 'owner');
        requireCsrf();

        reviewModerate((int) $id, 'published', (int) $_SESSION['user_id']);

        setFlash('success', 'Отзыв опубликован.');
        redirect('/admin/reviews');
    }

    public function reject(string $id): void
    {
        requireRole('shift_admin', 'owner');
        requireCsrf();

        reviewModerate((int) $id, 'rejected', (int) $_SESSION['user_id']);

        setFlash('success', 'Отзыв отклонён.');
        redirect('/admin/reviews');
    }
}
