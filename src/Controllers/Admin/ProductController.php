<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

/**
 * Список Товаров в админке — /admin/products (phase-7.md, Таск 7;
 * FR-ADM-001). Доступ — `owner` и `content_editor` (Фрилансер): другие
 * роли `requireRole()` отправляет на их домашнюю страницу.
 */
final class ProductController
{
    private const PER_PAGE = 20;
    private const SEARCH_MAX_LENGTH = 64;
    private const PAGE_MAX_DIGITS = 9;
    private const STATUSES = ['active', 'inactive'];

    public function index(): void
    {
        requireRole('owner', 'content_editor');

        $queryInput = input('q', '');
        $query = is_string($queryInput) ? mb_substr(trim($queryInput), 0, self::SEARCH_MAX_LENGTH) : '';

        $categories = categoryAll();
        $categoryInput = input('category', '');
        $categoryId = is_string($categoryInput) && ctype_digit($categoryInput) && strlen($categoryInput) <= self::PAGE_MAX_DIGITS
            ? (int) $categoryInput
            : 0;
        $knownCategoryIds = array_map(static fn (array $row): int => (int) $row['id'], $categories);
        if (!in_array($categoryId, $knownCategoryIds, true)) {
            $categoryId = 0;
        }

        $statusInput = input('status', '');
        $status = is_string($statusInput) && in_array($statusInput, self::STATUSES, true) ? $statusInput : '';

        $total = productAdminCount($query, $categoryId, $status);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        $pageInput = input('page', '1');
        $page = is_string($pageInput) && ctype_digit($pageInput) && strlen($pageInput) <= self::PAGE_MAX_DIGITS
            ? min(max(1, (int) $pageInput), $totalPages)
            : 1;

        $role = (string) $_SESSION['user_role'];

        render('admin/products', [
            'pageTitle'  => 'Товары — PetPark',
            'roleLabel'  => adminRoleLabel($role),
            'homeUrl'    => homePathForRole($role),
            'userRole'   => $role,
            'products'   => productAdminList($query, $categoryId, $status, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'categories' => $categories,
            'query'      => $query,
            'categoryId' => $categoryId,
            'status'     => $status,
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
        ]);
    }
}
