<?php

declare(strict_types=1);

/**
 * Очередь заявок на Возврат — /admin/returns (phase-6.md, Таск 6; FR-RET-002).
 * Таблица по паттерну orders.php.
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var array<int, array<string, mixed>> $returns returnListForAdmin()
 * @var string|null $status Активный фильтр по order_returns.status
 * @var int $page
 * @var int $totalPages
 * @var int $total
 */

$statusOptions = [
    ''          => 'Все статусы',
    'submitted' => 'Подана',
    'in_review' => 'На рассмотрении',
    'approved'  => 'Одобрен',
    'rejected'  => 'Отклонён',
    'completed' => 'Завершён',
];

$pageUrl = static function (int $targetPage) use ($status): string {
    $query = [];
    if ($status !== null) {
        $query['status'] = $status;
    }
    if ($targetPage > 1) {
        $query['page'] = $targetPage;
    }

    return '/admin/returns' . ($query === [] ? '' : '?' . http_build_query($query));
};

ob_start();
?>
<div class="d-md-flex d-block align-items-center justify-content-between my-4">
    <div>
        <h1 class="mb-0">Возвраты</h1>
        <p class="mb-0 text-muted">Найдено: <?= $total ?></p>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <form method="get" action="/admin/returns" class="row g-2 align-items-center w-100">
            <div class="col-12 col-md-4">
                <label for="returns-status" class="visually-hidden">Статус</label>
                <select id="returns-status" name="status" class="form-select">
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?= e((string) $value) ?>"<?= ($status ?? '') === (string) $value ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-auto">
                <button type="submit" class="btn btn-primary">Применить</button>
            </div>
        </form>
    </div>
    <div class="card-body">
        <?php if ($returns === []): ?>
            <p class="mb-0 text-muted">Заявок не найдено.</p>
        <?php else: ?>
            <div class="table-responsive position-relative">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Заказ</th>
                            <th scope="col">Покупатель</th>
                            <th scope="col">Сумма, ₽</th>
                            <th scope="col">Фото</th>
                            <th scope="col">Статус</th>
                            <th scope="col">Подана</th>
                            <th scope="col"><span class="visually-hidden">Действия</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($returns as $row): ?>
                            <tr>
                                <th scope="row">№<?= (int) $row['order_id'] ?></th>
                                <td>
                                    <?= e((string) ($row['contact_name'] ?? '')) ?>
                                    <div class="text-muted fs-12"><?= e((string) ($row['contact_phone'] ?? '')) ?></div>
                                </td>
                                <td><?= e(cartFormatMoney((string) $row['total'])) ?></td>
                                <td><?= (int) $row['photo_count'] ?></td>
                                <td>
                                    <?php $badgeStatus = (string) $row['status']; ?>
                                    <?php include __DIR__ . '/../components/admin/return-status-badge.php'; ?>
                                </td>
                                <td><?= e(date('d.m.Y H:i', (int) strtotime((string) $row['created_at']))) ?></td>
                                <td><a href="/admin/returns/<?= (int) $row['id'] ?>" class="btn btn-sm btn-outline-primary">Открыть</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <nav aria-label="Страницы списка Возвратов">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                        <a class="page-link" href="<?= e($pageUrl(max(1, $page - 1))) ?>">Назад</a>
                    </li>
                    <?php for ($number = 1; $number <= $totalPages; $number++): ?>
                        <li class="page-item<?= $number === $page ? ' active' : '' ?>">
                            <a class="page-link" href="<?= e($pageUrl($number)) ?>"<?= $number === $page ? ' aria-current="page"' : '' ?>><?= $number ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item<?= $page >= $totalPages ? ' disabled' : '' ?>">
                        <a class="page-link" href="<?= e($pageUrl(min($totalPages, $page + 1))) ?>">Вперёд</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
