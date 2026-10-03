<?php

declare(strict_types=1);

/**
 * Свой профиль Специалиста — /specialist/profile (phase-8.md, Таск 7).
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var array{name: string, position: string, bio: string} $values
 * @var array<string, string> $errors Ошибки по полям
 * @var string $photoUrl Текущее фото или заглушка
 * @var string|null $publicUrl Публичная страница профиля
 * @var int $nameLimit
 * @var int $positionLimit
 * @var int $bioLimit
 * @var string|null $success
 * @var string|null $error
 */

ob_start();
?>
<div class="d-md-flex d-block align-items-center justify-content-between my-4">
    <div>
        <h1 class="mb-0">Мой профиль</h1>
        <p class="mb-0 text-muted">Эти данные видны посетителям на странице вашего профиля. Телефон и email на сайте не показываются</p>
    </div>
    <?php if ($publicUrl !== null): ?>
        <div class="mt-3 mt-md-0">
            <a href="<?= e($publicUrl) ?>" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">Открыть на сайте</a>
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

<form method="post" action="/specialist/profile" enctype="multipart/form-data" class="card mb-4" novalidate>
    <?= csrfField() ?>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label for="profile-name" class="form-label">Имя</label>
                <input type="text" class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>" id="profile-name" name="name" maxlength="<?= $nameLimit ?>" required autocomplete="off" value="<?= e($values['name']) ?>">
                <?php if (isset($errors['name'])): ?>
                    <div class="invalid-feedback"><?= e($errors['name']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-6">
                <label for="profile-position" class="form-label">Должность</label>
                <input type="text" class="form-control<?= isset($errors['position']) ? ' is-invalid' : '' ?>" id="profile-position" name="position" maxlength="<?= $positionLimit ?>" placeholder="Например, Грумер" autocomplete="off" value="<?= e($values['position']) ?>">
                <?php if (isset($errors['position'])): ?>
                    <div class="invalid-feedback"><?= e($errors['position']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-12">
                <label for="profile-bio" class="form-label">Биография</label>
                <textarea class="form-control<?= isset($errors['bio']) ? ' is-invalid' : '' ?>" id="profile-bio" name="bio" rows="6" maxlength="<?= $bioLimit ?>" aria-describedby="profile-bio-help"><?= e($values['bio']) ?></textarea>
                <div class="form-text" id="profile-bio-help">Простой текст, до <?= $bioLimit ?> символов. Абзацы разделяйте переводом строки.</div>
                <?php if (isset($errors['bio'])): ?>
                    <div class="invalid-feedback"><?= e($errors['bio']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-12">
                <label for="profile-photo" class="form-label">Фото</label>
                <div class="mb-2">
                    <img src="<?= e($photoUrl) ?>" alt="Текущее фото: <?= e($values['name']) ?>" class="img-thumbnail" width="160" height="160">
                </div>
                <input type="file" class="form-control<?= isset($errors['photo']) ? ' is-invalid' : '' ?>" id="profile-photo" name="photo" accept="image/jpeg,image/png,image/webp" aria-describedby="profile-photo-help">
                <div class="form-text" id="profile-photo-help">JPEG, PNG или WebP, до <?= intdiv(RETURN_PHOTO_MAX_BYTES, 1024 * 1024) ?> МБ. Оставьте пустым, чтобы не менять фото.</div>
                <?php if (isset($errors['photo'])): ?>
                    <div class="invalid-feedback"><?= e($errors['photo']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card-footer">
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </div>
</form>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
