<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\MarketplaceSync;

/**
 * Маркетплейсы в админке — /admin/marketplaces (phase-9.md, Таск 2;
 * FR-MARKET-001, BR-007, ADR-001). Просмотр — `shift_admin`/`owner`,
 * синхронизация — только `owner`.
 */
final class MarketplaceController
{
    private const PER_PAGE = 20;

    private const MARKETPLACE_LABELS = [
        'wildberries' => 'Wildberries',
        'ozon' => 'Ozon',
    ];

    private const STATUS_LISTED = 'listed';
    private const STATUS_DELISTED = 'delisted';
    private const STATUS_NOT_SYNCED = 'not_synced';

    public function index(): void
    {
        requireRole('shift_admin', 'owner');

        $total = marketplaceListingCountForAdmin();
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        $pageInput = input('page', '1');
        $page = is_string($pageInput) && ctype_digit($pageInput)
            ? min(max(1, (int) $pageInput), $totalPages)
            : 1;

        $rows = marketplaceListingListForAdmin(self::PER_PAGE, ($page - 1) * self::PER_PAGE);
        $variants = array_map(static fn(array $row): array => $row + [
            'status' => self::statusesFor($row),
        ], $rows);

        $role = (string) $_SESSION['user_role'];

        render('admin/marketplaces', [
            'pageTitle'    => 'Маркетплейсы — PetPark',
            'roleLabel'    => adminRoleLabel($role),
            'homeUrl'      => homePathForRole($role),
            'userRole'     => $role,
            'marketplaces' => self::MARKETPLACE_LABELS,
            'variants'     => $variants,
            'page'         => $page,
            'totalPages'   => $totalPages,
            'total'        => $total,
            'success'      => getFlash('success'),
            'error'        => getFlash('error'),
        ]);
    }

    public function sync(string $marketplace): void
    {
        requireRole('owner');
        requireCsrf();

        if (!isset(self::MARKETPLACE_LABELS[$marketplace])) {
            http_response_code(404);
            render('errors/404');
            return;
        }

        $result = (new MarketplaceSync())->syncCatalog($marketplace);

        $pageInput = input('page', '1');
        $backUrl = '/admin/marketplaces'
            . (is_string($pageInput) && ctype_digit($pageInput) && (int) $pageInput > 1
                ? '?' . http_build_query(['page' => (int) $pageInput])
                : '');

        setFlash('success', sprintf(
            '%s: выгружено Вариантов — %d, снято — %d.',
            self::MARKETPLACE_LABELS[$marketplace],
            $result['listed'],
            $result['delisted']
        ));
        redirect($backUrl);
    }

    /**
     * Статус Варианта по каждой площадке: строка есть — выгружен; строки
     * нет и Вариант выгружаемый — не синхронизирован; иначе — снят.
     *
     * @param array<string, mixed> $row
     * @return array<string, string>
     */
    private static function statusesFor(array $row): array
    {
        $listable = marketplaceIsListable(
            (int) $row['stock_quantity'],
            (int) $row['reserved_quantity'],
            (bool) $row['variant_active'],
            (bool) $row['product_active']
        );

        $statuses = [];
        foreach (array_keys(self::MARKETPLACE_LABELS) as $marketplace) {
            $statuses[$marketplace] = match (true) {
                $row["{$marketplace}_price"] !== null => self::STATUS_LISTED,
                $listable => self::STATUS_NOT_SYNCED,
                default => self::STATUS_DELISTED,
            };
        }

        return $statuses;
    }
}
