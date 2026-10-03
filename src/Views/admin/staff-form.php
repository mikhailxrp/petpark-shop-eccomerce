<?php

declare(strict_types=1);

/**
 * Новый сотрудник — /admin/staff/new (phase-7.md, Таск 5; FR-ADM-003).
 * Пароль в форме не вводится: генерируется и уходит на email.
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var list<string> $roles Роли, доступные создающему (staffRolesCreatableBy())
 * @var array<string, mixed> $values
 * @var array<string, string> $errors Ошибки по полям
 * @var list<array<string, mixed>> $services Активные Услуги для блока Специалиста
 * @var string|null $error
 */

ob_start();
?>
<div class="d-md-flex d-block align-items-center justify-content-between my-4">
    <div>
        <h1 class="mb-0">Новый сотрудник</h1>
        <p class="mb-0 text-muted">Пароль будет сгенерирован и отправлен на указанный email</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="/admin/staff" class="btn btn-outline-secondary btn-sm">К списку</a>
    </div>
</div>

<?php if ($error !== null): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"><i class="fe fe-x" aria-hidden="true"></i></button>
    </div>
<?php endif; ?>

<form method="post" action="/admin/staff/new" class="card mb-4" novalidate>
    <?= csrfField() ?>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label for="staff-name" class="form-label">Имя</label>
                <input type="text" class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>" id="staff-name" name="name" maxlength="100" required autocomplete="off" value="<?= e($values['name']) ?>">
                <?php if (isset($errors['name'])): ?>
                    <div class="invalid-feedback"><?= e($errors['name']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-6">
                <label for="staff-email" class="form-label">Email</label>
                <input type="email" class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>" id="staff-email" name="email" maxlength="150" required autocomplete="off" value="<?= e($values['email']) ?>">
                <?php if (isset($errors['email'])): ?>
                    <div class="invalid-feedback"><?= e($errors['email']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-6">
                <label for="staff-phone" class="form-label">Телефон</label>
                <input type="tel" class="form-control<?= isset($errors['phone']) ? ' is-invalid' : '' ?>" id="staff-phone" name="phone" maxlength="20" placeholder="Необязательно" autocomplete="off" value="<?= e($values['phone']) ?>">
                <?php if (isset($errors['phone'])): ?>
                    <div class="invalid-feedback"><?= e($errors['phone']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-6">
                <label for="staff-role" class="form-label">Роль</label>
                <select id="staff-role" name="role" class="form-select<?= isset($errors['role']) ? ' is-invalid' : '' ?>" required>
                    <option value="">Выберите роль</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= e($role) ?>"<?= $role === $values['role'] ? ' selected' : '' ?>><?= e(adminRoleLabel($role)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['role'])): ?>
                    <div class="invalid-feedback"><?= e($errors['role']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <?php include __DIR__ . '/../components/specialist-schedule-fields.php'; ?>
    </div>
    <div class="card-footer">
        <button type="submit" class="btn btn-primary">Создать сотрудника</button>
    </div>
</form>
<script type="module" src="/admin/js/staff-form.js"></script>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
