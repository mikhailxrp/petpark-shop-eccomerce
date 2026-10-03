<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use DateTimeImmutable;

/**
 * Отчёты — /admin/reports (phase-7.md, Таск 10; FR-ADM-004). Выручка и число
 * Заказов, топ Товаров, Услуги по видам за день/неделю/месяц. Доступ — только
 * `owner` (финансовые данные): остальным 404, а не редирект, как у requireRole().
 */
final class ReportController
{
    public function index(): void
    {
        ensureSessionStarted();

        if (!isAuthenticated()) {
            redirect('/admin/login');
        }

        $role = (string) ($_SESSION['user_role'] ?? '');
        if ($role !== 'owner') {
            http_response_code(404);
            render('errors/404');
            return;
        }

        $period = reportNormalizePeriod(input('period'));
        $date = reportNormalizeDate(input('date'), new DateTimeImmutable('today'));
        $bounds = reportBounds($period, $date);
        $from = $bounds['from']->format(REPORT_DATETIME_FORMAT);
        $to = $bounds['to']->format(REPORT_DATETIME_FORMAT);

        render('admin/reports', [
            'pageTitle'   => 'Отчёты — PetPark',
            'roleLabel'   => adminRoleLabel($role),
            'homeUrl'     => homePathForRole($role),
            'userRole'    => $role,
            'period'      => $period,
            'periods'     => [
                REPORT_PERIOD_DAY   => 'День',
                REPORT_PERIOD_WEEK  => 'Неделя',
                REPORT_PERIOD_MONTH => 'Месяц',
            ],
            'date'        => $date->format(REPORT_DATE_FORMAT),
            'periodLabel' => reportPeriodLabel($period, $bounds),
            'orders'      => reportRevenueSeries(reportDays($bounds), reportOrdersByDay($from, $to)),
            'topProducts' => reportTopProducts($from, $to, REPORT_TOP_PRODUCTS_LIMIT),
            'services'    => reportServicesBreakdown(reportServicesByKind($from, $to)),
        ]);
    }
}
