<?php

declare(strict_types=1);

/**
 * Склад — /admin/stock (phase-3.md, Таск 7; FR-STOCK-001, ADR-001).
 * Заглушка МойСклад: поле «остаток в МойСклад» + «Синхронизировать».
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var array<int, array<string, mixed>> $variants productVariantListForStock()
 * @var string $query Поисковая строка
 * @var int $page
 * @var int $totalPages
 * @var int $total
 * @var string|null $success
 * @var string|null $error
 */

$pageUrl = static function (int $targetPage) use ($query): string {
    $params = [];
    if ($query !== '') {
        $params['q'] = $query;
    }
    if ($targetPage > 1) {
        $params['page'] = $targetPage;
    }

    return '/admin/stock' . ($params === [] ? '' : '?' . http_build_query($params));
};

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
    <h1 class="mb-0">Склад</h1>
    <p class="mb-0 text-muted">МойСклад (демо): введите остаток и нажмите «Синхронизировать». Найдено: <?= $total ?></p>
</div>

<div class="card">
    <div class="card-header">
        <form method="get" action="/admin/stock" class="row g-2 align-items-center w-100">
            <div class="col-12 col-md-4">
                <label for="stock-query" class="visually-hidden">Название или артикул</label>
                <input type="search" id="stock-query" name="q" class="form-control" maxlength="64" placeholder="Название или артикул" value="<?= e($query) ?>">
            </div>
            <div class="col-12 col-md-auto">
                <button type="submit" class="btn btn-primary">Найти</button>
            </div>
        </form>
    </div>
    <div class="card-body">
        <?php if ($variants === []): ?>
            <p class="mb-0 text-muted">Вариантов не найдено.</p>
        <?php else: ?>
            <div class="table-responsive position-relative">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Артикул</th>
                            <th scope="col">Товар</th>
                            <th scope="col">Остаток</th>
                            <th scope="col">Резерв</th>
                            <th scope="col">Синхронизация</th>
                            <th scope="col">Остаток в МойСклад</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($variants as $row): ?>
                            <?php
                            $variantId = (int) $row['variant_id'];
                            $syncedAt = $row['moysklad_synced_at'] !== null
                                ? date('d.m.Y H:i', (int) strtotime((string) $row['moysklad_synced_at']))
                                : 'не синхронизировался';
                            ?>
                            <tr>
                                <th scope="row"><?= e((string) $row['sku']) ?></th>
                                <td><?= e((string) $row['name']) ?></td>
                                <td><?= (int) $row['stock_quantity'] ?></td>
                                <td><?= (int) $row['reserved_quantity'] ?></td>
                                <td class="text-muted fs-12"><?= e($syncedAt) ?></td>
                                <td>
                                    <form method="post" action="/admin/stock/<?= $variantId ?>/sync" class="row g-2 align-items-center flex-nowrap">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="q" value="<?= e($query) ?>">
                                        <input type="hidden" name="page" value="<?= $page ?>">
                                        <div class="col">
                                            <label for="external-<?= $variantId ?>" class="visually-hidden">Остаток в МойСклад для <?= e((string) $row['sku']) ?></label>
                                            <input type="number" id="external-<?= $variantId ?>" name="external_quantity" class="form-control form-control-sm" min="0" max="1000000" step="1" required value="<?= (int) $row['stock_quantity'] ?>">
                                        </div>
                                        <div class="col-auto">
                                            <button type="submit" class="btn btn-sm btn-primary">Синхронизировать</button>
                                        </div>
                                    </form>
                                </td>
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
