<?php

declare(strict_types=1);

/**
 * Редактирование статической страницы — /admin/pages/{id} (phase-8.md, Таск 9).
 * Блок фото выводится только у страниц с галереей (`$hasGallery`).
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var array<string, mixed> $page
 * @var array{title: string, body: string, seo_title: string, seo_description: string} $values
 * @var array<string, string> $errors Ошибки по полям
 * @var string $publicUrl
 * @var bool $hasGallery
 * @var array<int, array{id: int, path: string, sort_order: int}> $images
 * @var int $imagesMax
 * @var int $photoMaxMb
 * @var int $titleLimit
 * @var int $bodyLimit
 * @var int $seoTitleLimit
 * @var int $seoDescriptionLimit
 * @var string|null $success
 * @var string|null $error
 */

$pageId = (int) $page['id'];

ob_start();
?>
<div class="d-md-flex d-block align-items-center justify-content-between my-4">
    <div>
        <h1 class="mb-0">Страница «<?= e((string) $page['title']) ?>»</h1>
        <p class="mb-0 text-muted">Адрес <?= e($publicUrl) ?> изменить нельзя</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="/admin/pages" class="btn btn-outline-secondary btn-sm">К списку</a>
        <a href="<?= e($publicUrl) ?>" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">Открыть на сайте</a>
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

<form method="post" action="/admin/pages/<?= $pageId ?>" class="card mb-4" novalidate>
    <?= csrfField() ?>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12">
                <label for="page-title" class="form-label">Заголовок</label>
                <input type="text" class="form-control<?= isset($errors['title']) ? ' is-invalid' : '' ?>" id="page-title" name="title" maxlength="<?= $titleLimit ?>" required autocomplete="off" value="<?= e($values['title']) ?>">
                <?php if (isset($errors['title'])): ?>
                    <div class="invalid-feedback"><?= e($errors['title']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-12">
                <label for="page-body" class="form-label">Текст</label>
                <textarea class="form-control<?= isset($errors['body']) ? ' is-invalid' : '' ?>" id="page-body" name="body" rows="14" required aria-describedby="page-body-help"><?= e($values['body']) ?></textarea>
                <div class="form-text" id="page-body-help">HTML: теги p, h2, h3, ul, ol, li, strong, em, a. Остальные теги и атрибуты удаляются при сохранении. До <?= $bodyLimit ?> символов.</div>
                <?php if (isset($errors['body'])): ?>
                    <div class="invalid-feedback"><?= e($errors['body']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-12">
                <label for="page-seo-title" class="form-label">SEO-заголовок</label>
                <input type="text" class="form-control<?= isset($errors['seo_title']) ? ' is-invalid' : '' ?>" id="page-seo-title" name="seo_title" maxlength="<?= $seoTitleLimit ?>" autocomplete="off" aria-describedby="page-seo-title-help" value="<?= e($values['seo_title']) ?>">
                <div class="form-text" id="page-seo-title-help">До <?= $seoTitleLimit ?> символов. Пусто — заголовок подставится автоматически.</div>
                <?php if (isset($errors['seo_title'])): ?>
                    <div class="invalid-feedback"><?= e($errors['seo_title']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-12">
                <label for="page-seo-description" class="form-label">SEO-описание</label>
                <textarea class="form-control<?= isset($errors['seo_description']) ? ' is-invalid' : '' ?>" id="page-seo-description" name="seo_description" rows="3" maxlength="<?= $seoDescriptionLimit ?>" aria-describedby="page-seo-description-help"><?= e($values['seo_description']) ?></textarea>
                <div class="form-text" id="page-seo-description-help">До <?= $seoDescriptionLimit ?> символов. Пусто — описание возьмётся из первых слов текста.</div>
                <?php if (isset($errors['seo_description'])): ?>
                    <div class="invalid-feedback"><?= e($errors['seo_description']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card-footer">
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </div>
</form>

<?php if ($hasGallery): ?>
    <section class="card mb-4" aria-labelledby="page-photos-title">
        <div class="card-header"><h2 class="card-title" id="page-photos-title">Фото страницы</h2></div>
        <div class="card-body">
            <?php if ($images === []): ?>
                <p class="text-muted">Фото пока нет.</p>
            <?php else: ?>
                <div class="row g-3 mb-4">
                    <?php foreach ($images as $index => $image): ?>
                        <div class="col-6 col-md-4 col-lg-3">
                            <img src="/uploads/<?= e(ltrim((string) $image['path'], '/')) ?>" alt="Фото <?= $index + 1 ?> на странице «<?= e((string) $page['title']) ?>»" class="img-thumbnail mb-2" loading="lazy">
                            <form method="post" action="/admin/pages/<?= $pageId ?>/images/<?= (int) $image['id'] ?>/delete">
                                <?= csrfField() ?>
                                <button type="submit" class="btn btn-outline-danger btn-sm">Удалить</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (count($images) < $imagesMax): ?>
                <form method="post" action="/admin/pages/<?= $pageId ?>/images" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <label for="page-photos" class="form-label">Добавить фото</label>
                    <input type="file" class="form-control mb-2" id="page-photos" name="photos[]" multiple required accept="image/jpeg,image/png,image/webp" aria-describedby="page-photos-help">
                    <div class="form-text mb-3" id="page-photos-help">JPEG, PNG или WebP, до <?= $photoMaxMb ?> МБ каждое. Всего на странице — до <?= $imagesMax ?> фото.</div>
                    <button type="submit" class="btn btn-primary">Загрузить</button>
                </form>
            <?php else: ?>
                <p class="mb-0 text-muted">Достигнут максимум — <?= $imagesMax ?> фото. Удалите лишнее, чтобы добавить новые.</p>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../../layouts/admin.php';
