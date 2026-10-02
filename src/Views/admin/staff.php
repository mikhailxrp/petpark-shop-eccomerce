<?php

declare(strict_types=1);

/**
 * Список персонала — /admin/staff (phase-7.md, Таск 5; FR-ADM-003).
 * Таблица из паттерна «userlist» Valex (admin-assembly.md); смена роли и
 * отключение показаны только там, где `can_manage` (Владелец, не он сам).
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var list<array<string, mixed>> $staff userListStaff() + can_manage
 * @var list<string> $assignable Роли, на которые Владелец может сменить роль
 * @var bool $canCreate
 * @var string|null $success
 * @var string|null $error
 */

ob_start();
?>
<div class="d-md-flex d-block align-items-center justify-content-between my-4">
    <div>
        <h1 class="mb-0">Сотрудники</h1>
        <p class="mb-0 text-muted">Всего: <?= count($staff) ?></p>
    </div>
    <?php if ($canCreate): ?>
        <div class="mt-3 mt-md-0">
            <a href="/admin/staff/new" class="btn btn-primary btn-sm">Добавить сотрудника</a>
        </div>
    <?php endif; ?>
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

<div class="card">
    <div class="card-header"><h2 class="card-title">Учётные записи персонала</h2></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table text-nowrap mb-0">
                <thead>
                    <tr>
                        <th scope="col">Имя</th>
                        <th scope="col">Email</th>
                        <th scope="col">Телефон</th>
                        <th scope="col">Роль</th>
                        <th scope="col">Статус</th>
                        <th scope="col"><span class="visually-hidden">Действия</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staff as $member): ?>
                        <?php $memberId = (int) $member['id']; ?>
                        <tr>
                            <td><?= e((string) $member['name']) ?></td>
                            <td><?= e((string) $member['email']) ?></td>
                            <td><?= e((string) ($member['phone'] ?? '—')) ?></td>
                            <td>
                                <?php if ($member['can_manage']): ?>
                                    <form method="post" action="/admin/staff/<?= $memberId ?>/role" class="d-flex gap-2">
                                        <?= csrfField() ?>
                                        <label for="staff-role-<?= $memberId ?>" class="visually-hidden">Роль: <?= e((string) $member['name']) ?></label>
                                        <select id="staff-role-<?= $memberId ?>" name="role" class="form-select form-select-sm">
                                            <?php foreach ($assignable as $role): ?>
                                                <option value="<?= e($role) ?>"<?= $role === $member['role'] ? ' selected' : '' ?>><?= e(adminRoleLabel($role)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-outline-primary btn-sm">Сменить</button>
                                    </form>
                                <?php else: ?>
                                    <?= e(adminRoleLabel((string) $member['role'])) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int) $member['is_active'] === 1): ?>
                                    <span class="badge bg-success">Активен</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Отключён</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($member['can_manage']): ?>
                                    <form method="post" action="/admin/staff/<?= $memberId ?>/active" class="d-inline">
                                        <?= csrfField() ?>
                                        <?php if ((int) $member['is_active'] === 1): ?>
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Отключить</button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-outline-success btn-sm">Включить</button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
