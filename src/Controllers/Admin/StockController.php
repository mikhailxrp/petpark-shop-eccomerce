<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\MoySklad;

/**
 * Склад в админке — /admin/stock (phase-3.md, Таск 7; FR-STOCK-001).
 * Заглушка МойСклад: сотрудник вводит «внешний» остаток Варианта, кнопка
 * «Синхронизировать» пишет его в stock_quantity. Доступ — `shift_admin`/`owner`.
 */
final class StockController
{
    private const PER_PAGE = 20;
    private const SEARCH_MAX_LENGTH = 64;
    private const QUANTITY_MAX = 1000000;
    private const QUANTITY_INVALID_ERROR = 'Остаток должен быть целым числом от 0 до 1 000 000.';

    public function index(): void
    {
        requireRole('shift_admin', 'owner');

        $queryInput = input('q', '');
        $query = is_string($queryInput) ? mb_substr(trim($queryInput), 0, self::SEARCH_MAX_LENGTH) : '';

        $total = productVariantCountForStock($query);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        $pageInput = input('page', '1');
        $page = is_string($pageInput) && ctype_digit($pageInput)
            ? min(max(1, (int) $pageInput), $totalPages)
            : 1;

        $role = (string) $_SESSION['user_role'];

        render('admin/stock', [
            'pageTitle'  => 'Склад — PetPark',
            'roleLabel'  => adminRoleLabel($role),
            'homeUrl'    => homePathForRole($role),
            'userRole'   => $role,
            'variants'   => productVariantListForStock($query, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'query'      => $query,
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
            'success'    => getFlash('success'),
            'error'      => getFlash('error'),
        ]);
    }

    public function sync(string $id): void
    {
        requireRole('shift_admin', 'owner');
        requireCsrf();

        $variantId = ctype_digit($id) ? (int) $id : 0;

        if ($variantId === 0 || !productVariantExists($variantId)) {
            http_response_code(404);
            render('errors/404');
            return;
        }

        $quantityInput = input('external_quantity');
        $quantity = is_string($quantityInput)
            && preg_match('/^\d{1,7}$/', trim($quantityInput)) === 1
            && (int) trim($quantityInput) <= self::QUANTITY_MAX
            ? (int) trim($quantityInput)
            : null;

        $backQuery = [];
        $queryInput = input('q', '');
        if (is_string($queryInput) && $queryInput !== '') {
            $backQuery['q'] = mb_substr(trim($queryInput), 0, self::SEARCH_MAX_LENGTH);
        }
        $pageInput = input('page', '1');
        if (is_string($pageInput) && ctype_digit($pageInput) && (int) $pageInput > 1) {
            $backQuery['page'] = (int) $pageInput;
        }
        $backUrl = '/admin/stock' . ($backQuery === [] ? '' : '?' . http_build_query($backQuery));

        if ($quantity === null) {
            setFlash('error', self::QUANTITY_INVALID_ERROR);
            redirect($backUrl);
        }

        (new MoySklad())->syncVariant($variantId, $quantity);

        setFlash('success', 'Остаток синхронизирован.');
        redirect($backUrl);
    }
}
