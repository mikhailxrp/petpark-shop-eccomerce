<?php

declare(strict_types=1);

/**
 * Карточка клиента — /admin/clients/{id} (phase-7.md, Таск 11; FR-MGR-002).
 * Блок Заказов — только для shift_admin/owner (`$showOrders`).
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var array<string, mixed> $client clientFind()
 * @var array<int, array<string, mixed>> $pets petsByUser()
 * @var array<int, array<string, mixed>> $visits clientVisits()
 * @var array<int, array<string, mixed>> $orders clientOrders(); пусто, если !$showOrders
 * @var bool $showOrders
 * @var int $listLimit
 */

ob_start();
?>
<div class="d-md-flex d-block align-items-center justify-content-between my-4">
    <div>
        <h1 class="mb-0"><?= e((string) $client['name']) ?></h1>
        <p class="mb-0 text-muted">Клиент с <?= e(date('d.m.Y', (int) strtotime((string) $client['created_at']))) ?></p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="/admin/clients" class="btn btn-outline-secondary btn-sm">К списку клиентов</a>
    </div>
</div>

<div class="row">
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Контакты</h2></div>
            <div class="card-body">
                <dl class="mb-0">
                    <dt>Телефон</dt>
                    <dd><?= ($client['phone'] ?? '') !== '' ? e((string) $client['phone']) : '—' ?></dd>
                    <dt>Email</dt>
                    <dd class="mb-0"><?= e((string) $client['email']) ?></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Питомцы</h2></div>
            <div class="card-body">
                <?php if ($pets === []): ?>
                    <p class="mb-0 text-muted">Питомцев нет.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($pets as $pet): ?>
                            <li>
                                <?= e((string) $pet['name']) ?>
                                <span class="text-muted">
                                    (<?= e((string) $pet['species']) ?><?= ($pet['breed'] ?? '') !== '' ? ', ' . e((string) $pet['breed']) : '' ?>)
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">История визитов</h2></div>
    <div class="card-body">
        <?php if ($visits === []): ?>
            <p class="mb-0 text-muted">Визитов нет.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle client-table">
                    <thead class="client-table__head">
                        <tr>
                            <th scope="col">Дата и время</th>
                            <th scope="col">Питомец</th>
                            <th scope="col">Услуги</th>
                            <th scope="col">Специалист</th>
                            <th scope="col">Статус</th>
                            <th scope="col"><span class="visually-hidden">Действия</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visits as $visit): ?>
                            <tr class="client-table__row">
                                <th scope="row" class="client-table__cell client-table__cell--main"><?= e(date('d.m.Y H:i', (int) strtotime((string) $visit['scheduled_at']))) ?></th>
                                <td class="client-table__cell" data-label="Питомец"><?= e((string) $visit['pet_name']) ?></td>
                                <td class="client-table__cell" data-label="Услуги"><?= e((string) ($visit['service_names'] ?? '')) ?></td>
                                <td class="client-table__cell" data-label="Специалист"><?= e((string) $visit['specialist_name']) ?></td>
                                <td class="client-table__cell" data-label="Статус">
                                    <?php $badgeStatus = (string) $visit['status']; ?>
                                    <?php include __DIR__ . '/../components/admin/booking-status-badge.php'; ?>
                                </td>
                                <td class="client-table__cell client-table__cell--actions"><a href="/admin/bookings/<?= (int) $visit['id'] ?>" class="btn btn-sm btn-outline-primary">Открыть</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($visits) >= $listLimit): ?>
                <p class="mb-0 mt-2 text-muted fs-12">Показаны последние <?= $listLimit ?>.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($showOrders): ?>
    <div class="card">
        <div class="card-header"><h2 class="card-title">Заказы</h2></div>
        <div class="card-body">
            <?php if ($orders === []): ?>
                <p class="mb-0 text-muted">Заказов нет.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle client-table">
                        <thead class="client-table__head">
                            <tr>
                                <th scope="col">№</th>
                                <th scope="col">Дата</th>
                                <th scope="col">Позиций</th>
                                <th scope="col">Сумма, ₽</th>
                                <th scope="col">Статус</th>
                                <th scope="col"><span class="visually-hidden">Действия</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr class="client-table__row">
                                    <th scope="row" class="client-table__cell client-table__cell--main">№<?= (int) $order['id'] ?></th>
                                    <td class="client-table__cell" data-label="Дата"><?= e(date('d.m.Y H:i', (int) strtotime((string) $order['created_at']))) ?></td>
                                    <td class="client-table__cell" data-label="Позиций"><?= (int) $order['items_count'] ?></td>
                                    <td class="client-table__cell" data-label="Сумма, ₽"><?= e(cartFormatMoney((string) $order['total'])) ?></td>
                                    <td class="client-table__cell" data-label="Статус">
                                        <?php $badgeStatus = (string) $order['status']; ?>
                                        <?php include __DIR__ . '/../components/admin/order-status-badge.php'; ?>
                                    </td>
                                    <td class="client-table__cell client-table__cell--actions"><a href="/admin/orders/<?= (int) $order['id'] ?>" class="btn btn-sm btn-outline-primary">Открыть</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($orders) >= $listLimit): ?>
                    <p class="mb-0 mt-2 text-muted fs-12">Показаны последние <?= $listLimit ?>.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
