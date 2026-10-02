<?php

declare(strict_types=1);

/**
 * Форма Товара — /admin/products/new и /admin/products/{id}/edit
 * (phase-7.md, Таск 8; FR-ADM-001). Владелец видит все поля, Фрилансер —
 * только название, описание и фото (сервер читает из его POST только их).
 * Цена, остаток и Варианты — Таск 9.
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var array<string, mixed>|null $product null — новый Товар
 * @var array<string, mixed> $values
 * @var array<string, string> $errors Ошибки по полям
 * @var array<int, array<string, mixed>> $images Фото правящегося Товара
 * @var array<int, array<string, mixed>> $categories categoryAll()
 * @var array<int, array<string, mixed>> $brands brandAll()
 * @var int $photosMax
 * @var int $photoMb
 * @var string|null $success
 * @var string|null $error
 */

$isOwner = $userRole === 'owner';
$isNew = $product === null;
$formAction = $isNew ? '/admin/products/new' : '/admin/products/' . (int) $product['id'];
$isActive = !$isNew && (int) $product['is_active'] === 1;

$fieldClass = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';

ob_start();
?>
<div class="d-md-flex d-block align-items-center justify-content-between my-4">
    <div>
        <h1 class="mb-0"><?= $isNew ? 'Новый товар' : e((string) $values['name']) ?></h1>
        <?php if (!$isNew && !$isActive): ?>
            <p class="mb-0"><span class="badge bg-light text-muted">Не активен</span></p>
        <?php endif; ?>
    </div>
    <div class="mt-3 mt-md-0 d-flex flex-wrap gap-2">
        <a href="/admin/products" class="btn btn-outline-secondary btn-sm">К списку</a>
        <?php if ($isOwner && !$isNew): ?>
            <form method="post" action="/admin/products/<?= (int) $product['id'] ?>/active">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-sm <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?>"><?= $isActive ? 'Деактивировать' : 'Активировать' ?></button>
            </form>
        <?php endif; ?>
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

<form method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data" class="card mb-4" novalidate>
    <?= csrfField() ?>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12">
                <label for="product-name" class="form-label">Название</label>
                <input type="text" class="form-control<?= $fieldClass('name') ?>" id="product-name" name="name" maxlength="200" required value="<?= e((string) $values['name']) ?>">
                <?php if (isset($errors['name'])): ?>
                    <div class="invalid-feedback"><?= e($errors['name']) ?></div>
                <?php endif; ?>
            </div>

            <?php if ($isOwner): ?>
                <div class="col-12">
                    <label for="product-slug" class="form-label">Адрес страницы</label>
                    <div class="input-group has-validation">
                        <span class="input-group-text">/product/</span>
                        <input type="text" class="form-control<?= $fieldClass('slug') ?>" id="product-slug" name="slug" maxlength="220" autocomplete="off" aria-describedby="product-slug-help" value="<?= e((string) $values['slug']) ?>">
                        <?php if (isset($errors['slug'])): ?>
                            <div class="invalid-feedback"><?= e($errors['slug']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-text" id="product-slug-help"><?= $isNew ? 'Оставьте пустым — адрес соберётся из названия.' : 'Смена адреса ломает старые ссылки на Товар — меняйте только при необходимости.' ?></div>
                </div>
                <div class="col-12 col-md-4">
                    <label for="product-category" class="form-label">Основная категория</label>
                    <select id="product-category" name="category_id" class="form-select<?= $fieldClass('category_id') ?>" required>
                        <option value="">Выберите категорию</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>"<?= (int) $category['id'] === (int) $values['category_id'] ? ' selected' : '' ?>><?= e((string) $category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['category_id'])): ?>
                        <div class="invalid-feedback"><?= e($errors['category_id']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-12 col-md-4">
                    <label for="product-secondary-category" class="form-label">Дополнительная категория</label>
                    <select id="product-secondary-category" name="secondary_category_id" class="form-select<?= $fieldClass('secondary_category_id') ?>">
                        <option value="">Нет</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>"<?= (int) $category['id'] === (int) $values['secondary_category_id'] ? ' selected' : '' ?>><?= e((string) $category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['secondary_category_id'])): ?>
                        <div class="invalid-feedback"><?= e($errors['secondary_category_id']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-12 col-md-4">
                    <label for="product-brand" class="form-label">Бренд</label>
                    <select id="product-brand" name="brand_id" class="form-select<?= $fieldClass('brand_id') ?>">
                        <option value="">Без бренда</option>
                        <?php foreach ($brands as $brand): ?>
                            <option value="<?= (int) $brand['id'] ?>"<?= (int) $brand['id'] === (int) $values['brand_id'] ? ' selected' : '' ?>><?= e((string) $brand['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['brand_id'])): ?>
                        <div class="invalid-feedback"><?= e($errors['brand_id']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="col-12">
                <label for="product-description" class="form-label">Описание</label>
                <textarea class="form-control<?= $fieldClass('description') ?>" id="product-description" name="description" rows="8" maxlength="20000"><?= e((string) ($values['description'] ?? '')) ?></textarea>
                <?php if (isset($errors['description'])): ?>
                    <div class="invalid-feedback"><?= e($errors['description']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <fieldset class="mt-4" id="product-photos">
            <legend class="h5">Фото</legend>

            <?php if ($images !== []): ?>
                <div class="row g-3 mb-3">
                    <?php foreach ($images as $image): ?>
                        <div class="col-6 col-md-3">
                            <div class="card h-100">
                                <div class="ratio ratio-1x1">
                                    <img src="<?= e('/uploads/' . ltrim((string) $image['path'], '/')) ?>" alt="<?= e((string) $values['name']) ?>" class="card-img-top object-fit-cover" loading="lazy">
                                </div>
                                <div class="card-body p-2">
                                    <div class="form-check">
                                        <input type="radio" class="form-check-input" id="product-main-<?= (int) $image['id'] ?>" name="main_image" value="<?= (int) $image['id'] ?>"<?= (int) $image['is_main'] === 1 ? ' checked' : '' ?>>
                                        <label class="form-check-label" for="product-main-<?= (int) $image['id'] ?>">Главное</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="product-remove-<?= (int) $image['id'] ?>" name="remove_images[]" value="<?= (int) $image['id'] ?>">
                                        <label class="form-check-label" for="product-remove-<?= (int) $image['id'] ?>">Удалить</label>
                                    </div>
                                    <label for="product-order-<?= (int) $image['id'] ?>" class="form-label mb-0 mt-1 fs-12">Порядок</label>
                                    <input type="number" class="form-control form-control-sm" id="product-order-<?= (int) $image['id'] ?>" name="image_order[<?= (int) $image['id'] ?>]" min="0" max="9999" value="<?= (int) $image['sort_order'] ?>">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <label for="product-photos-input" class="form-label">Добавить фото</label>
            <input type="file" class="form-control<?= $fieldClass('photos') ?>" id="product-photos-input" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple aria-describedby="product-photos-help" data-max-files="<?= $photosMax ?>" data-max-mb="<?= $photoMb ?>">
            <?php if (isset($errors['photos'])): ?>
                <div class="invalid-feedback"><?= e($errors['photos']) ?></div>
            <?php endif; ?>
            <div class="form-text" id="product-photos-help">JPEG, PNG или WebP, до <?= $photoMb ?> МБ каждое, у Товара — не больше <?= $photosMax ?> фото. Порядок — по возрастанию числа.</div>
            <div class="row g-3 mt-1" id="product-photos-preview" aria-live="polite"></div>
        </fieldset>
    </div>
    <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $isNew ? 'Создать товар' : 'Сохранить' ?></button>
    </div>
</form>
<script type="module" src="/admin/js/product-form.js"></script>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
