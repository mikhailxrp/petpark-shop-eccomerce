<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

/**
 * Сводки после входа персонала (phase-7.md, Таск 12; FR-MGR-001, FR-MGR-003).
 * `/admin` (Администратор смены, Владелец) — новые Заказы, Записи на сегодня,
 * неделя календаря всех Специалистов, последние клиенты. `/specialist` —
 * свой календарь и свои клиенты (рисует BookingController); блока Заказов и
 * общего списка клиентов у Специалиста нет. Только чтение, правки — по ссылкам.
 */
final class DashboardController
{
    private const NEW_ORDERS_LIMIT = 5;
    private const RECENT_CLIENTS_LIMIT = 5;
    private const NEW_ORDER_STATUS = 'new';
    private const WEEKDAYS = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

    public function index(): void
    {
        requireRole('shift_admin', 'owner');

        bookingReleaseExpired();

        $role = (string) $_SESSION['user_role'];
        $today = date('Y-m-d');
        $week = bookingWeekBounds($today);

        $monday = new \DateTimeImmutable($week['from']);
        $weekDays = [];
        foreach (self::WEEKDAYS as $i => $weekday) {
            $date = $monday->modify("+{$i} days");
            $weekDays[$date->format('Y-m-d')] = [
                'label'    => $weekday . ', ' . $date->format('d.m'),
                'isToday'  => $date->format('Y-m-d') === $today,
                'bookings' => [],
            ];
        }
        foreach (bookingsForWeek($week['from'], $week['to'], null) as $booking) {
            $weekDays[substr((string) $booking['scheduled_at'], 0, 10)]['bookings'][] = $booking;
        }

        render('admin/dashboard', [
            'pageTitle'      => 'Панель управления — PetPark',
            'roleLabel'      => adminRoleLabel($role),
            'homeUrl'        => homePathForRole($role),
            'userRole'       => $role,
            'newOrders'      => orderListForAdmin(self::NEW_ORDER_STATUS, self::NEW_ORDERS_LIMIT, 0),
            'newOrdersTotal' => orderCountForAdmin(self::NEW_ORDER_STATUS),
            'todayBookings'  => $weekDays[$today]['bookings'],
            'weekDays'       => $weekDays,
            'recentClients'  => clientRecent(null, self::RECENT_CLIENTS_LIMIT),
        ]);
    }

    public function specialist(): void
    {
        requireRole('specialist');

        // Календарь своих Записей (phase-4.md, Таск 7); без профиля Специалиста
        // календарю не по чему фильтровать — остаётся пустая сводка.
        $specialistId = bookingSpecialistIdByUser((int) $_SESSION['user_id']);
        if ($specialistId === null) {
            $role = (string) $_SESSION['user_role'];
            render('admin/dashboard', [
                'pageTitle' => 'Панель управления — PetPark',
                'roleLabel' => adminRoleLabel($role),
                'homeUrl'   => homePathForRole($role),
                'userRole'  => $role,
            ]);
            return;
        }

        (new BookingController())->specialistWeek($specialistId);
    }
}
