<?php

declare(strict_types=1);

/**
 * Карточка Записи — /admin/bookings/{id} (phase-4.md, Таск 7; FR-SV-010).
 * Действия — только для `confirmed`; `slot_selected` («Ждёт оплаты») —
 * просмотр, его подтверждает оплата Депозита.
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var array<string, mixed> $booking bookingFindForStaff()
 * @var string $backUrl
 * @var bool $canAct
 * @var string|null $success
 * @var string|null $error
 */

$depositLabel = match ((string) $booking['deposit_status']) {
    'held'      => 'Удерживается',
    'returned'  => 'Возвращён',
    'forfeited' => 'Не возвращается (неявка)',
    default     => 'Не требуется',
};
$actionBase = '/admin/bookings/' . (int) $booking['id'];

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

<div class="d-md-flex d-block align-items-center justify-content-between my-4">
    <div>
        <h1 class="mb-0">Запись №<?= (int) $booking['id'] ?></h1>
        <p class="mb-0 text-muted">
            Визит <?= e(date('d.m.Y H:i', (int) strtotime((string) $booking['scheduled_at']))) ?>
        </p>
    </div>
    <div class="mt-3 mt-md-0">
        <?php $badgeStatus = (string) $booking['status']; ?>
        <?php include __DIR__ . '/../components/admin/booking-status-badge.php'; ?>
        <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary btn-sm ms-2">К календарю</a>
    </div>
</div>

<div class="row">
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Клиент и Питомец</h2></div>
            <div class="card-body">
                <dl class="mb-0">
                    <dt>Имя</dt>
                    <dd><?= e((string) $booking['customer_name']) ?></dd>
                    <dt>Телефон</dt>
                    <dd><?= ($booking['customer_phone'] ?? '') !== '' ? e((string) $booking['customer_phone']) : '—' ?></dd>
                    <dt>Email</dt>
                    <dd><?= e((string) $booking['customer_email']) ?></dd>
                    <dt>Питомец</dt>
                    <dd class="mb-0"><?= e((string) $booking['pet_name']) ?> (<?= e((string) $booking['pet_species']) ?>)</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Визит и Депозит</h2></div>
            <div class="card-body">
                <dl class="mb-0">
                    <dt>Специалист</dt>
                    <dd><?= e((string) $booking['specialist_name']) ?></dd>
                    <dt>Депозит</dt>
                    <dd>
                        <?= e($depositLabel) ?>
                        <?php if ($booking['deposit_amount'] !== null): ?>
                            — <?= e((string) $booking['deposit_amount']) ?> ₽
                        <?php endif; ?>
                    </dd>
                    <dt>Сделка в AmoCRM</dt>
                    <dd class="mb-0"><?= ($booking['amocrm_id'] ?? null) !== null ? e((string) $booking['amocrm_id']) : '—' ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Услуги</h2></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table text-nowrap align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Услуга</th>
                        <th scope="col">Длительность, мин</th>
                        <th scope="col">Цена, ₽</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($booking['services'] as $service): ?>
                        <tr>
                            <td><?= e((string) $service['service_name']) ?></td>
                            <td><?= (int) $service['duration_minutes'] ?></td>
                            <td><?= e((string) $service['price']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Действия</h2></div>
    <div class="card-body">
        <?php if (!$canAct): ?>
            <p class="mb-0 text-muted">
                <?= $booking['status'] === 'slot_selected'
                    ? 'Запись ждёт оплаты Депозита — подтвердится автоматически после оплаты.'
                    : 'Для этой Записи действий нет.' ?>
            </p>
        <?php else: ?>
            <div class="d-flex flex-wrap gap-2">
                <form method="post" action="<?= e($actionBase) ?>/complete">
                    <?= csrfField() ?>
                    <button type="submit" class="btn btn-sm btn-primary">Завершена</button>
                </form>
                <form method="post" action="<?= e($actionBase) ?>/no-show">
                    <?= csrfField() ?>
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Неявка</button>
                </form>
                <a href="<?= e($actionBase) ?>/reschedule" class="btn btn-sm btn-outline-primary">Перенести</a>
                <form method="post" action="<?= e($actionBase) ?>/cancel">
                    <?= csrfField() ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger">Отменить</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
