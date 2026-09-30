<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

/**
 * Заказы в админке — /admin/orders, /admin/orders/{id} (phase-3.md,
 * Таск 2; FR-ORD-001, FR-ORD-002). Только чтение. Доступ —
 * `shift_admin`/`owner`; Специалист Заказов не видит (FR-ORD-003).
 */
final class OrderController
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        requireRole('shift_admin', 'owner');

        $statusInput = input('status', '');
        $status = is_string($statusInput) && array_key_exists($statusInput, ORDER_STATUS_TRANSITIONS)
            ? $statusInput
            : null;

        $total = orderCountForAdmin($status);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        $pageInput = input('page', '1');
        $page = is_string($pageInput) && ctype_digit($pageInput)
            ? min(max(1, (int) $pageInput), $totalPages)
            : 1;

        $role = (string) $_SESSION['user_role'];

        render('admin/orders', [
            'pageTitle'  => 'Заказы — PetPark',
            'roleLabel'  => adminRoleLabel($role),
            'homeUrl'    => homePathForRole($role),
            'userRole'   => $role,
            'orders'     => orderListForAdmin($status, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'status'     => $status,
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
        ]);
    }

    public function show(string $id): void
    {
        requireRole('shift_admin', 'owner');

        $order = ctype_digit($id) ? orderFindForAdmin((int) $id) : null;

        if ($order === null) {
            http_response_code(404);
            render('errors/404');
            return;
        }

        $role = (string) $_SESSION['user_role'];

        render('admin/order', [
            'pageTitle' => 'Заказ №' . $order['id'] . ' — PetPark',
            'roleLabel' => adminRoleLabel($role),
            'homeUrl'   => homePathForRole($role),
            'userRole'  => $role,
            'order'     => $order,
            'items'     => orderItemsForOrder((int) $order['id']),
        ]);
    }
}
