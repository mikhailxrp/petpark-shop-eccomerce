<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

/**
 * Клиенты — /admin/clients, /admin/clients/{id} (phase-7.md, Таск 11;
 * FR-MGR-002, FR-MGR-003). `shift_admin`/`owner` видят всех клиентов, их
 * Заказы и все визиты; Специалист — только клиентов со своими Записями, с
 * полным телефоном, без Заказов и без визитов к другим Специалистам. Чужой,
 * несуществующий клиент и сотрудник по id в URL дают 404.
 */
final class ClientController
{
    private const STAFF_ROLES = ['specialist', 'shift_admin', 'owner'];
    private const ORDER_ROLES = ['shift_admin', 'owner'];

    public function index(): void
    {
        requireRole(...self::STAFF_ROLES);

        $role = (string) $_SESSION['user_role'];
        $specialistId = $this->scopeSpecialistId($role);

        $queryInput = input('q', '');
        $terms = clientSearchTerms($queryInput);

        // Специалист без профиля ничьих клиентов не видит: null в Model
        // означал бы «без ограничения».
        $hasAccess = $role !== 'specialist' || $specialistId !== null;
        $total = $hasAccess ? clientCount($terms, $specialistId) : 0;
        $totalPages = max(1, (int) ceil($total / CLIENT_PER_PAGE));
        $page = clientNormalizePage(input('page', '1'), $totalPages);

        render('admin/clients', [
            'pageTitle'  => 'Клиенты — PetPark',
            'roleLabel'  => adminRoleLabel($role),
            'homeUrl'    => homePathForRole($role),
            'userRole'   => $role,
            'clients'    => $total > 0
                ? clientList($terms, $specialistId, CLIENT_PER_PAGE, ($page - 1) * CLIENT_PER_PAGE)
                : [],
            'query'      => is_string($queryInput) ? trim($queryInput) : '',
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
        ]);
    }

    public function show(string $id): void
    {
        requireRole(...self::STAFF_ROLES);

        $role = (string) $_SESSION['user_role'];
        $specialistId = $this->scopeSpecialistId($role);
        $client = ctype_digit($id) ? clientFind((int) $id) : null;

        $allowed = $client !== null
            && ($role !== 'specialist'
                || ($specialistId !== null && clientHasBookingWith((int) $client['id'], $specialistId)));
        if (!$allowed) {
            http_response_code(404);
            render('errors/404');
            return;
        }

        $clientId = (int) $client['id'];
        $showOrders = in_array($role, self::ORDER_ROLES, true);

        render('admin/client', [
            'pageTitle'  => $client['name'] . ' — клиенты — PetPark',
            'roleLabel'  => adminRoleLabel($role),
            'homeUrl'    => homePathForRole($role),
            'userRole'   => $role,
            'client'     => $client,
            'pets'       => petsByUser($clientId),
            'visits'     => clientVisits($clientId, $specialistId, CLIENT_CARD_LIST_LIMIT),
            'orders'     => $showOrders ? clientOrders($clientId, CLIENT_CARD_LIST_LIMIT) : [],
            'showOrders' => $showOrders,
            'listLimit'  => CLIENT_CARD_LIST_LIMIT,
        ]);
    }

    /** specialists.id для Специалиста; null — роль не ограничена одним Специалистом или профиля нет. */
    private function scopeSpecialistId(string $role): ?int
    {
        return $role === 'specialist'
            ? bookingSpecialistIdByUser((int) $_SESSION['user_id'])
            : null;
    }
}
