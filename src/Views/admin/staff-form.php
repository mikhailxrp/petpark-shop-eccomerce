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

const STAFF_DAY_OFF_LABELS = [
    1 => 'Понедельник',
    2 => 'Вторник',
    3 => 'Среда',
    4 => 'Четверг',
    5 => 'Пятница',
    6 => 'Суббота',
    0 => 'Воскресенье',
];

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

        <fieldset class="mt-4" id="staff-specialist-fields" data-specialist-role="specialist">
            <legend class="h5">Услуги и график Специалиста</legend>
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <label for="staff-work-start" class="form-label">Начало работы</label>
                    <input type="time" class="form-control<?= isset($errors['work_start']) ? ' is-invalid' : '' ?>" id="staff-work-start" name="work_start" value="<?= e((string) $values['work_start']) ?>">
                    <?php if (isset($errors['work_start'])): ?>
                        <div class="invalid-feedback"><?= e($errors['work_start']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-6 col-md-3">
                    <label for="staff-work-end" class="form-label">Конец работы</label>
                    <input type="time" class="form-control<?= isset($errors['work_end']) ? ' is-invalid' : '' ?>" id="staff-work-end" name="work_end" value="<?= e((string) $values['work_end']) ?>">
                    <?php if (isset($errors['work_end'])): ?>
                        <div class="invalid-feedback"><?= e($errors['work_end']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-12 col-md-6">
                    <label for="staff-day-off" class="form-label">Выходной</label>
                    <select id="staff-day-off" name="day_off" class="form-select<?= isset($errors['day_off']) ? ' is-invalid' : '' ?>">
                        <option value="">Без выходного</option>
                        <?php foreach (STAFF_DAY_OFF_LABELS as $dayNumber => $dayLabel): ?>
                            <option value="<?= $dayNumber ?>"<?= (string) $dayNumber === (string) $values['day_off'] ? ' selected' : '' ?>><?= e($dayLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['day_off'])): ?>
                        <div class="invalid-feedback"><?= e($errors['day_off']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-12">
                    <span class="form-label d-block" id="staff-services-label">Услуги</span>
                    <div role="group" aria-labelledby="staff-services-label">
                        <?php foreach ($services as $service): ?>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input<?= isset($errors['service_ids']) ? ' is-invalid' : '' ?>" id="staff-service-<?= (int) $service['id'] ?>" name="service_ids[]" value="<?= (int) $service['id'] ?>"<?= in_array((int) $service['id'], $values['service_ids'], true) ? ' checked' : '' ?>>
                                <label class="form-check-label" for="staff-service-<?= (int) $service['id'] ?>"><?= e((string) $service['name']) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($services === []): ?>
                        <p class="text-muted mb-0">Активных Услуг нет.</p>
                    <?php endif; ?>
                    <?php if (isset($errors['service_ids'])): ?>
                        <div class="text-danger small mt-1"><?= e($errors['service_ids']) ?></div>
                    <?php endif; ?>
                    <div class="form-text">Без Услуг Специалист создастся, но в форме Записи не появится.</div>
                </div>
            </div>
        </fieldset>
    </div>
    <div class="card-footer">
        <button type="submit" class="btn btn-primary">Создать сотрудника</button>
    </div>
</form>
<script type="module" src="/admin/js/staff-form.js"></script>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
