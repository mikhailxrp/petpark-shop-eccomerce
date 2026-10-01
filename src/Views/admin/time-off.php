<?php

declare(strict_types=1);

/**
 * Закрытие слотов — /admin/time-off (phase-4.md, Таск 9; FR-SV-010).
 * Закрываются целые дни; Покупатель в эти дни слотов не видит.
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var list<array{id: int, specialist_id: int, specialist_name: string, date_from: string, date_to: string, reason: ?string}> $periods
 * @var list<array{id: int, name: string}> $specialists Пусто — Специалист закрывает дни только себе
 * @var string $today "Y-m-d"
 * @var string $backUrl
 * @var string|null $success
 * @var string|null $error
 */

ob_start();
?>
<div class="d-md-flex d-block align-items-center justify-content-between my-4">
    <div>
        <h1 class="mb-0">Закрытие слотов</h1>
        <p class="mb-0 text-muted">Отпуск, болезнь — в эти дни запись к Специалисту недоступна</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary btn-sm">К календарю</a>
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

<form method="post" action="/admin/time-off" class="card mb-4 needs-validation" novalidate>
    <?= csrfField() ?>
    <div class="card-header"><h2 class="card-title">Закрыть дни</h2></div>
    <div class="card-body">
        <div class="row g-3">
            <?php if ($specialists !== []): ?>
                <div class="col-12 col-md-6 col-lg-3">
                    <label for="time-off-specialist" class="form-label">Специалист</label>
                    <select id="time-off-specialist" name="specialist_id" class="form-select" required>
                        <?php foreach ($specialists as $specialist): ?>
                            <option value="<?= (int) $specialist['id'] ?>"><?= e($specialist['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-12 col-md-6 col-lg-3">
                <label for="time-off-from" class="form-label">С даты</label>
                <input type="date" class="form-control" id="time-off-from" name="date_from" min="<?= e($today) ?>" required>
                <div class="invalid-feedback">Укажите дату начала.</div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label for="time-off-to" class="form-label">По дату (включительно)</label>
                <input type="date" class="form-control" id="time-off-to" name="date_to" min="<?= e($today) ?>" required>
                <div class="invalid-feedback">Укажите дату окончания.</div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label for="time-off-reason" class="form-label">Причина</label>
                <input type="text" class="form-control" id="time-off-reason" name="reason" maxlength="<?= (int) SPECIALIST_TIME_OFF_REASON_MAX ?>" placeholder="Необязательно">
            </div>
        </div>
    </div>
    <div class="card-footer">
        <button type="submit" class="btn btn-primary">Закрыть дни</button>
    </div>
</form>

<div class="card">
    <div class="card-header"><h2 class="card-title">Закрытые периоды</h2></div>
    <div class="card-body">
        <?php if ($periods === []): ?>
            <p class="mb-0 text-muted">Закрытых периодов нет.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table text-nowrap mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Специалист</th>
                            <th scope="col">Период</th>
                            <th scope="col">Причина</th>
                            <th scope="col"><span class="visually-hidden">Действия</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($periods as $period): ?>
                            <?php
                            $from = date('d.m.Y', (int) strtotime($period['date_from']));
                            $to = date('d.m.Y', (int) strtotime($period['date_to']));
                            ?>
                            <tr>
                                <td><?= e($period['specialist_name']) ?></td>
                                <td><?= e($from === $to ? $from : $from . ' — ' . $to) ?></td>
                                <td><?= e($period['reason'] ?? '—') ?></td>
                                <td class="text-end">
                                    <form method="post" action="/admin/time-off/<?= (int) $period['id'] ?>/delete" class="d-inline">
                                        <?= csrfField() ?>
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Открыть дни</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
