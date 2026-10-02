<?php

declare(strict_types=1);

/**
 * Список Товаров — /admin/products (phase-7.md, Таск 7; FR-ADM-001).
 * Только просмотр: цена и остаток — источник МойСклад, формы правки
 * Товара — Таск 8.
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var array<int, array<string, mixed>> $products productAdminList()
 * @var array<int, array<string, mixed>> $categories categoryAll()
 * @var string $query
 * @var int $categoryId 0 = все
 * @var string $status '' | active | inactive
 * @var int $page
 * @var int $totalPages
 * @var int $total
 */

$pageUrl = static function (int $targetPage) use ($query, $categoryId, $status): string {
    $params = [];
    if ($query !== '') {
        $params['q'] = $query;
    }
    if ($categoryId > 0) {
        $params['category'] = $categoryId;
    }
    if ($status !== '') {
        $params['status'] = $status;
    }
    if ($targetPage > 1) {
        $params['page'] = $targetPage;
    }

    return '/admin/products' . ($params === [] ? '' : '?' . http_build_query($params));
};

// Компактная пагинация: 1 … текущая±1 … последняя (null = многоточие).
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

$attributesLabels = [
    'confirmed' => ['Подтверждены', 'bg-success-transparent'],
    'drafts'    => ['Есть черновики ИИ', 'bg-warning-transparent'],
    'none'      => ['Нет', 'bg-light text-muted'],
];

ob_start();
?>
<div class="my-4">
    <h1 class="mb-0">Товары</h1>
    <p class="mb-0 text-muted">Найдено: <?= $total ?></p>
</div>

<div class="card">
    <div class="card-header">
        <form method="get" action="/admin/products" class="row g-2 align-items-center w-100">
            <div class="col-12 col-md-4">
                <label for="product-query" class="visually-hidden">Название</label>
                <input type="search" id="product-query" name="q" class="form-control" maxlength="64" placeholder="Название Товара" value="<?= e($query) ?>">
            </div>
            <div class="col-12 col-md-3">
                <label for="product-category" class="visually-hidden">Категория</label>
                <select id="product-category" name="category" class="form-select">
                    <option value="">Все категории</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"<?= (int) $category['id'] === $categoryId ? ' selected' : '' ?>><?= e((string) $category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label for="product-status" class="visually-hidden">Статус</label>
                <select id="product-status" name="status" class="form-select">
                    <option value="">Любой статус</option>
                    <option value="active"<?= $status === 'active' ? ' selected' : '' ?>>Активные</option>
                    <option value="inactive"<?= $status === 'inactive' ? ' selected' : '' ?>>Не активные</option>
                </select>
            </div>
            <div class="col-12 col-md-auto">
                <button type="submit" class="btn btn-primary">Найти</button>
            </div>
        </form>
    </div>
    <div class="card-body">
        <?php if ($products === []): ?>
            <p class="mb-0 text-muted">Товаров не найдено.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Фото</th>
                            <th scope="col">Название</th>
                            <th scope="col">Категория</th>
                            <th scope="col">Бренд</th>
                            <th scope="col">Цена</th>
                            <th scope="col">Наличие</th>
                            <th scope="col">Статус</th>
                            <th scope="col">Характеристики</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $row): ?>
                            <?php
                            $imageUrl = $row['image_path'] !== null
                                ? '/uploads/' . ltrim((string) $row['image_path'], '/')
                                : null;
                            if ($row['price_min'] === null) {
                                $priceLabel = '—';
                            } else {
                                $priceFrom = seoFormatPrice($row['price_min']);
                                $priceTo = seoFormatPrice($row['price_max']);
                                $priceLabel = ($priceFrom === $priceTo ? $priceFrom : $priceFrom . '–' . $priceTo) . ' ₽';
                            }
                            $inStock = (int) ($row['available_quantity'] ?? 0) > 0;
                            $isActive = (int) $row['is_active'] === 1;
                            [$attributesText, $attributesClass] = $attributesLabels[(string) $row['attributes_status']];
                            ?>
                            <tr>
                                <td>
                                    <?php if ($imageUrl !== null): ?>
                                        <img src="<?= e($imageUrl) ?>" alt="<?= e((string) $row['name']) ?>" class="rounded object-fit-cover" width="48" height="48" loading="lazy">
                                    <?php else: ?>
                                        <span class="text-muted fs-12">нет фото</span>
                                    <?php endif; ?>
                                </td>
                                <th scope="row"><?= e((string) $row['name']) ?></th>
                                <td><?= e((string) $row['category_name']) ?></td>
                                <td><?= e((string) ($row['brand_name'] ?? '—')) ?></td>
                                <td class="text-nowrap"><?= e($priceLabel) ?></td>
                                <td>
                                    <span class="badge <?= $inStock ? 'bg-success-transparent' : 'bg-danger-transparent' ?>"><?= $inStock ? 'В наличии' : 'Нет в наличии' ?></span>
                                </td>
                                <td>
                                    <span class="badge <?= $isActive ? 'bg-success-transparent' : 'bg-light text-muted' ?>"><?= $isActive ? 'Активен' : 'Не активен' ?></span>
                                </td>
                                <td><span class="badge <?= e($attributesClass) ?>"><?= e($attributesText) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <nav aria-label="Страницы списка Товаров">
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
