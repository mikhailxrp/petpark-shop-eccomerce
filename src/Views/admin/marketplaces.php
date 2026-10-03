<?php

declare(strict_types=1);

/**
 * Маркетплейсы — /admin/marketplaces (phase-9.md, Таск 2; FR-MARKET-001, ADR-001).
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var array<string, string> $marketplaces Код площадки → название
 * @var array<int, array<string, mixed>> $variants marketplaceListingListForAdmin() + `status`
 * @var int $page
 * @var int $totalPages
 * @var int $total
 * @var string|null $success
 * @var string|null $error
 */

$statusView = [
    'listed'     => ['Выгружен', 'bg-success'],
    'delisted'   => ['Снят', 'bg-secondary'],
    'not_synced' => ['Не синхронизирован', 'bg-warning'],
];

$pageUrl = static fn(int $targetPage): string => '/admin/marketplaces'
    . ($targetPage > 1 ? '?' . http_build_query(['page' => $targetPage]) : '');

// Компактная пагинация: 1 … текущая±1 … последняя (null = многоточие),
// чтобы влезала в 320px при любом числе страниц.
$pageWindow = [];
$previous = 0;
foreach (array_unique([1, $page - 1, $page, $page + 1, $totalPages]) as $number) {
    if ($number < 1 || $number > $totalPages) {
        continue;
    }
    if ($number - $previous > 1) {
        $pageWindow[] = null;
    }
    $pageWindow[] = $number;
    $previous = $number;
}

ob_start();
?>
<?php if ($success !== null): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= e($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"><i class="fe fe-x" aria-hidden="true"></i></button>
    </div>
<?php endif; ?>
<?php if ($error !== null): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"><i class="fe fe-x" aria-hidden="true"></i></button>
    </div>
<?php endif; ?>

<div class="my-4">
    <h1 class="mb-0">Маркетплейсы</h1>
    <p class="mb-0 text-muted">Выгрузка Вариантов на площадки с наценкой +<?= MARKETPLACE_MARKUP_PERCENT ?>%. Найдено: <?= $total ?></p>
</div>

<div class="alert alert-info" role="status">
    Демо-режим: реального API площадок нет, «синхронизация» пересчитывает цены и наличие в самой системе.
</div>

<div class="card">
    <?php if ($userRole === 'owner'): ?>
        <div class="card-header">
            <div class="row g-2 w-100">
                <?php foreach ($marketplaces as $code => $label): ?>
                    <div class="col-12 col-md-auto">
                        <form method="post" action="/admin/marketplaces/<?= e($code) ?>/sync">
                            <?= csrfField() ?>
                            <input type="hidden" name="page" value="<?= $page ?>">
                            <button type="submit" class="btn btn-primary w-100">Синхронизировать <?= e($label) ?></button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    <div class="card-body">
        <?php if ($variants === []): ?>
            <p class="mb-0 text-muted">Вариантов нет.</p>
        <?php else: ?>
            <div class="table-responsive position-relative">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Артикул</th>
                            <th scope="col">Товар</th>
                            <th scope="col">Цена сайта, ₽</th>
                            <?php foreach ($marketplaces as $label): ?>
                                <th scope="col"><?= e($label) ?>, ₽</th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($variants as $row): ?>
                            <?php $sitePrice = (string) ($row['discount_price'] ?? $row['price']); ?>
                            <tr>
                                <th scope="row"><?= e((string) $row['sku']) ?></th>
                                <td><?= e((string) $row['name']) ?></td>
                                <td><?= e($sitePrice) ?></td>
                                <?php foreach ($marketplaces as $code => $label): ?>
                                    <?php
                                    [$statusLabel, $statusClass] = $statusView[$row['status'][$code]];
                                    $syncedAt = $row["{$code}_synced_at"] !== null
                                        ? date('d.m.Y H:i', (int) strtotime((string) $row["{$code}_synced_at"]))
                                        : null;
                                    ?>
                                    <td>
                                        <?php if ($row["{$code}_price"] !== null): ?>
                                            <div><?= e((string) $row["{$code}_price"]) ?></div>
                                        <?php endif; ?>
                                        <span class="badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
                                        <?php if ($syncedAt !== null): ?>
                                            <div class="text-muted fs-12"><?= e($syncedAt) ?></div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <nav aria-label="Страницы списка Вариантов">
                <ul class="pagination pagination-sm justify-content-center flex-wrap mb-0">
                    <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                        <a class="page-link" href="<?= e($pageUrl(max(1, $page - 1))) ?>" aria-label="Назад"><span aria-hidden="true">&lsaquo;</span></a>
                    </li>
                    <?php foreach ($pageWindow as $number): ?>
                        <?php if ($number === null): ?>
                            <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                        <?php else: ?>
                            <li class="page-item<?= $number === $page ? ' active' : '' ?>">
                                <a class="page-link" href="<?= e($pageUrl($number)) ?>"<?= $number === $page ? ' aria-current="page"' : '' ?>><?= $number ?></a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <li class="page-item<?= $page >= $totalPages ? ' disabled' : '' ?>">
                        <a class="page-link" href="<?= e($pageUrl(min($totalPages, $page + 1))) ?>" aria-label="Вперёд"><span aria-hidden="true">&rsaquo;</span></a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
