<?php

declare(strict_types=1);

/**
 * Список Заказов — /admin/orders (phase-3.md, Таск 2; FR-ORD-002).
 * Таблица по паттерну data-tables.html (Valex), admin-assembly.md.
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var array<int, array<string, mixed>> $orders orderListForAdmin()
 * @var string|null $status Активный фильтр по orders.status
 * @var int $page
 * @var int $totalPages
 * @var int $total
 */

$statusOptions = [
    ''                 => 'Все статусы',
    'new'              => 'Новый',
    'confirmed'        => 'Подтверждён',
    'assembled'        => 'Собран',
    'shipped'          => 'Отправлен',
    'ready_for_pickup' => 'Готов к выдаче',
    'delivered'        => 'Доставлен',
    'picked_up'        => 'Выдан',
    'cancelled'        => 'Отменён',
];

$pageUrl = static function (int $targetPage) use ($status): string {
    $query = [];
    if ($status !== null) {
        $query['status'] = $status;
    }
    if ($targetPage > 1) {
        $query['page'] = $targetPage;
    }

    return '/admin/orders' . ($query === [] ? '' : '?' . http_build_query($query));
};

ob_start();
?>
<div class="d-md-flex d-block align-items-center justify-content-between my-4">
    <div>
        <h1 class="mb-0">Заказы</h1>
        <p class="mb-0 text-muted">Найдено: <?= $total ?></p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="/admin/orders/new" class="btn btn-primary">Создать заказ</a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <form method="get" action="/admin/orders" class="row g-2 align-items-center w-100">
            <div class="col-12 col-md-4">
                <label for="orders-status" class="visually-hidden">Статус</label>
                <select id="orders-status" name="status" class="form-select">
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
        <?php if ($orders === []): ?>
            <p class="mb-0 text-muted">Заказов не найдено.</p>
        <?php else: ?>
            <div class="table-responsive position-relative">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col">№</th>
                            <th scope="col">Покупатель</th>
                            <th scope="col">Позиций</th>
                            <th scope="col">Сумма, ₽</th>
                            <th scope="col">Получение / оплата</th>
                            <th scope="col">Статус</th>
                            <th scope="col">Дата</th>
                            <th scope="col"><span class="visually-hidden">Действия</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $row): ?>
                            <?php
                            $deliveryLabel = match ($row['delivery_method']) {
                                'pickup'  => 'Самовывоз',
                                'courier' => 'Курьер',
                                default   => (string) $row['delivery_method'],
                            };
                            $paymentLabel = match ($row['payment_method']) {
                                'card_online'              => 'Картой на сайте',
                                'cash_or_card_on_delivery' => 'При получении',
                                default                    => (string) $row['payment_method'],
                            };
                            ?>
                            <tr>
                                <th scope="row">№<?= (int) $row['id'] ?></th>
                                <td>
                                    <?= e((string) ($row['contact_name'] ?? '')) ?>
                                    <div class="text-muted fs-12"><?= e((string) ($row['contact_phone'] ?? '')) ?></div>
                                </td>
                                <td><?= (int) $row['items_count'] ?></td>
                                <td><?= e(cartFormatMoney((string) $row['total'])) ?></td>
                                <td>
                                    <?= e($deliveryLabel) ?>
                                    <div class="text-muted fs-12"><?= e($paymentLabel) ?></div>
                                </td>
                                <td>
                                    <?php $badgeStatus = (string) $row['status']; ?>
                                    <?php include __DIR__ . '/../components/admin/order-status-badge.php'; ?>
                                </td>
                                <td><?= e(date('d.m.Y H:i', (int) strtotime((string) $row['created_at']))) ?></td>
                                <td><a href="/admin/orders/<?= (int) $row['id'] ?>" class="btn btn-sm btn-outline-primary">Открыть</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <nav aria-label="Страницы списка Заказов">
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
