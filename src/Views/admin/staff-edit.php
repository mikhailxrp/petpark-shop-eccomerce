<?php

declare(strict_types=1);

/**
 * Профиль Специалиста — /admin/staff/{id}/profile (phase-7.md, Таск 13).
 * Правится только график и набор Услуг; имя, телефон, email и пароль — нет.
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var array<string, mixed> $member userFindStaffById()
 * @var array<string, mixed> $values
 * @var array<string, string> $errors Ошибки по полям
 * @var list<array<string, mixed>> $services Активные Услуги
 * @var list<array{id: int, when: string, client_name: string, reasons: list<string>}> $conflicts Будущие Записи вне графика
 * @var string|null $success
 * @var string|null $error
 */

ob_start();
?>
<div class="d-md-flex d-block align-items-center justify-content-between my-4">
    <div>
        <h1 class="mb-0">Профиль Специалиста</h1>
        <p class="mb-0 text-muted"><?= e((string) $member['name']) ?> · <?= e((string) $member['email']) ?></p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="/admin/staff" class="btn btn-outline-secondary btn-sm">К списку</a>
    </div>
</div>

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

<?php if ($conflicts !== []): ?>
    <div class="alert alert-warning" role="alert">
        <h2 class="h6 alert-heading">Будущие Записи вне графика или Услуг: <?= count($conflicts) ?></h2>
        <p class="mb-2">Записи не отменены. Свяжитесь с клиентами или перенесите их вручную.</p>
        <ul class="mb-0">
            <?php foreach ($conflicts as $conflict): ?>
                <li>
                    <a href="/admin/bookings/<?= (int) $conflict['id'] ?>"><?= e($conflict['when']) ?></a>
                    — <?= e($conflict['client_name']) ?> (<?= e(implode(', ', $conflict['reasons'])) ?>)
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="/admin/staff/<?= (int) $member['id'] ?>/profile" class="card mb-4" novalidate>
    <?= csrfField() ?>
    <div class="card-body">
        <?php include __DIR__ . '/../components/specialist-schedule-fields.php'; ?>
    </div>
    <div class="card-footer">
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </div>
</form>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
