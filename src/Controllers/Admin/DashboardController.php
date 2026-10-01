<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

/**
 * Пустые сводки-заглушки после входа персонала — /admin и /specialist
 * (phase-1.md, Таск 7). Реальные данные (заказы, календарь, финотчёты)
 * появятся в Фазах 3/7 — здесь только подтверждение, что вход и
 * ролевой доступ работают.
 */
final class DashboardController
{
    public function index(): void
    {
        requireRole('shift_admin', 'content_editor', 'owner');

        $this->renderDashboard();
    }

    public function specialist(): void
    {
        requireRole('specialist');

        // Календарь своих Записей (phase-4.md, Таск 7); без профиля Специалиста
        // календарю не по чему фильтровать — остаётся пустая сводка.
        $specialistId = bookingSpecialistIdByUser((int) $_SESSION['user_id']);
        if ($specialistId === null) {
            $this->renderDashboard();
            return;
        }

        (new BookingController())->specialistWeek($specialistId);
    }

    private function renderDashboard(): void
    {
        $role = (string) $_SESSION['user_role'];

        render('admin/dashboard', [
            'pageTitle' => 'Панель управления — PetPark',
            'roleLabel' => adminRoleLabel($role),
            'homeUrl'   => homePathForRole($role),
            'userRole'  => $role,
        ]);
    }
}
