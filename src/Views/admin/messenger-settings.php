<?php

declare(strict_types=1);

/**
 * Ссылки на мессенджеры — /admin/settings/messengers (phase-6.md, Таск 7;
 * FR-NOTIF-003). Ссылки читают кнопка мессенджеров, запасной блок чата и
 * соц-иконки в шапке/футере.
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var array<string, string> $labels      код → подпись
 * @var array<string, string> $urls        код → текущая ссылка
 * @var list<string> $enabled              коды из CHANNELS_ENABLED
 * @var int $maxLength
 * @var array<string, string> $fieldErrors код → ошибка
 * @var string|null $success
 * @var string|null $error
 */

ob_start();
?>
<div class="d-md-flex d-block align-items-center justify-content-between my-4">
    <div>
        <h1 class="mb-0">Мессенджеры</h1>
        <p class="mb-0 text-muted">Ссылки на диалог с магазином: кнопка на сайте, чат и соц-иконки</p>
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

<form method="post" action="/admin/settings/messengers" class="card mb-4" novalidate>
    <?= csrfField() ?>
    <div class="card-header"><h2 class="card-title">Ссылки на диалог</h2></div>
    <div class="card-body">
        <p class="text-muted">
            Ссылка должна начинаться с <code>https://</code>, например <code>https://t.me/petpark_rostov</code>.
            Пустое поле — мессенджер на сайте не показывается.
        </p>
        <div class="row g-3">
            <?php foreach ($labels as $code => $label): ?>
                <?php
                $fieldId = 'messenger-link-' . $code;
                $fieldError = $fieldErrors[$code] ?? null;
                $isEnabled = in_array($code, $enabled, true);
                ?>
                <div class="col-12 col-lg-6">
                    <label for="<?= e($fieldId) ?>" class="form-label"><?= e($label) ?></label>
                    <input type="url" class="form-control<?= $fieldError !== null ? ' is-invalid' : '' ?>"
                           id="<?= e($fieldId) ?>" name="link_<?= e($code) ?>"
                           value="<?= e($urls[$code] ?? '') ?>" maxlength="<?= (int) $maxLength ?>"
                           placeholder="https://"
                           <?= $fieldError !== null ? 'aria-describedby="' . e($fieldId) . '-error"' : '' ?>>
                    <?php if ($fieldError !== null): ?>
                        <div class="invalid-feedback" id="<?= e($fieldId) ?>-error"><?= e($fieldError) ?></div>
                    <?php endif; ?>
                    <?php if (!$isEnabled): ?>
                        <div class="form-text">Канал выключен в <code>CHANNELS_ENABLED</code> — на сайте не показывается.</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card-footer">
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </div>
</form>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
