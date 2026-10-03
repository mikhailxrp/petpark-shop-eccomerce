<?php

declare(strict_types=1);

/**
 * Сводка Администратора смены / Владельца — /admin (phase-7.md, Таск 12;
 * FR-MGR-001). Без данных (Специалист без профиля на /specialist) —
 * только приветствие.
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var list<array<string, mixed>> $newOrders orderListForAdmin()
 * @var int $newOrdersTotal
 * @var list<array<string, mixed>> $todayBookings
 * @var array<string, array{label: string, isToday: bool, bookings: list<array<string, mixed>>}> $weekDays
 * @var list<array<string, mixed>> $recentClients clientRecent()
 */

ob_start();
?>
<div class="d-md-flex d-block align-items-center justify-content-between my-4">
    <div>
        <h1 class="mb-0">Добро пожаловать!</h1>
        <p class="mb-0 text-muted">Роль: <?= e($roleLabel) ?></p>
    </div>
</div>

<?php if (!isset($newOrders)): ?>
    <div class="card">
        <div class="card-body">
            <p class="mb-0">Профиль Специалиста ещё не создан — обратитесь к Администратору смены.</p>
        </div>
    </div>
<?php else: ?>
    <div class="row">
        <div class="col-12 col-xl-6">
            <section class="card mb-4" aria-labelledby="dashboard-orders-title">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="card-title mb-0" id="dashboard-orders-title">Новые заказы: <?= $newOrdersTotal ?></h2>
                    <a href="/admin/orders" class="btn btn-sm btn-outline-primary">Все заказы</a>
                </div>
                <div class="card-body">
                    <?php if ($newOrders === []): ?>
                        <p class="mb-0 text-muted">Новых заказов нет.</p>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($newOrders as $order): ?>
                                <li class="mb-2">
                                    <a href="/admin/orders/<?= (int) $order['id'] ?>">№<?= (int) $order['id'] ?></a>
                                    · <?= e((string) $order['contact_name']) ?>
                                    · <?= e(cartFormatMoney((string) $order['total'])) ?>
                                    <span class="text-muted fs-12"><?= e(date('d.m H:i', (int) strtotime((string) $order['created_at']))) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-6">
            <section class="card mb-4" aria-labelledby="dashboard-today-title">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="card-title mb-0" id="dashboard-today-title">Записи на сегодня: <?= count($todayBookings) ?></h2>
                    <a href="/admin/bookings" class="btn btn-sm btn-outline-primary">Весь календарь</a>
                </div>
                <div class="card-body">
                    <?php if ($todayBookings === []): ?>
                        <p class="mb-0 text-muted">На сегодня записей нет.</p>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($todayBookings as $booking): ?>
                                <li class="mb-2">
                                    <a href="/admin/bookings/<?= (int) $booking['id'] ?>"><?= e(substr((string) $booking['scheduled_at'], 11, 5)) ?></a>
                                    · <?= e((string) $booking['pet_name']) ?>
                                    (<?= e((string) $booking['customer_name']) ?>)
                                    · <?= e((string) $booking['specialist_name']) ?>
                                    <?php $badgeStatus = (string) $booking['status']; ?>
                                    <?php include __DIR__ . '/../components/admin/booking-status-badge.php'; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>

    <section class="card mb-4" aria-labelledby="dashboard-week-title">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="card-title mb-0" id="dashboard-week-title">Неделя: все Специалисты</h2>
            <a href="/admin/bookings" class="btn btn-sm btn-outline-primary">Весь календарь</a>
        </div>
        <div class="card-body">
            <div class="row g-2">
                <?php foreach ($weekDays as $day): ?>
                    <div class="col-12 col-md-6 col-xl">
                        <div class="booking-day<?= $day['isToday'] ? ' booking-day--today' : '' ?>">
                            <h3 class="booking-day__header fs-14"><?= e($day['label']) ?></h3>
                            <?php if ($day['bookings'] === []): ?>
                                <p class="booking-day__empty">Записей нет</p>
                            <?php else: ?>
                                <ul class="booking-day__list">
                                    <?php foreach ($day['bookings'] as $booking): ?>
                                        <li>
                                            <a href="/admin/bookings/<?= (int) $booking['id'] ?>" class="booking-tile">
                                                <span class="booking-tile__time"><?= e(substr((string) $booking['scheduled_at'], 11, 5)) ?></span>
                                                <span class="booking-tile__pet"><?= e((string) $booking['pet_name']) ?></span>
                                                <span class="booking-tile__specialist"><?= e((string) $booking['specialist_name']) ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php $clientsTitle = 'Последние клиенты'; ?>
    <?php include __DIR__ . '/../components/admin/dashboard-clients.php'; ?>
<?php endif; ?>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
