<?php

declare(strict_types=1);

/**
 * Форма Товара — /admin/products/new и /admin/products/{id}/edit
 * (phase-7.md, Таск 8; FR-ADM-001). Владелец видит все поля, Фрилансер —
 * только название, описание и фото (сервер читает из его POST только их).
 * Таск 9 (FR-ADM-002): у существующего Товара Владелец ещё видит блоки
 * Вариантов и Характеристик — каждый со своей формой и своим POST.
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var array<string, mixed>|null $product null — новый Товар
 * @var array<string, mixed> $values
 * @var array<string, string> $errors Ошибки по полям
 * @var array<int, array<string, mixed>> $images Фото правящегося Товара
 * @var bool $descriptionSuggested Описание в поле — предложение ИИ, ещё не сохранённое
 * @var array<int, array<string, mixed>> $categories categoryAll()
 * @var array<int, array<string, mixed>> $brands brandAll()
 * @var int $photosMax
 * @var int $photoMb
 * @var string|null $success
 * @var string|null $error
 * @var list<string> $attributeNames Характеристики Товара (ATTRIBUTE_EXTRACT_NAMES)
 * @var list<string> $variantAttributes Свойства Варианта (вес упаковки, вкус)
 * @var int $attributeMaxLength
 * @var int $stockMax
 * @var array<int, array<string, mixed>>|null $variants null — блок не показывается
 * @var array<string, string> $attributes Подтверждённые Характеристики Товара
 * @var array<string, list<string>> $dictionary attr_name => значения справочника
 * @var array<string, string> $suggestions Предложения ИИ (attr_name => значение) из последнего разбора
 * @var array{target: int|string, values: array<string, mixed>, errors: array<string, string>}|null $variantForm
 */

$isOwner = $userRole === 'owner';
$isNew = $product === null;
$showCatalogBlocks = $isOwner && !$isNew && $variants !== null;
$showDescriptionAi = $isOwner && !$isNew;
$attributeLabel = static fn (string $name): string => str_replace('_', ' ', $name);
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
                <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-1">
                    <label for="product-description" class="form-label mb-0">Описание</label>
                    <?php if ($showDescriptionAi): ?>
                        <button type="submit" form="description-ai-form" class="btn btn-outline-primary btn-sm" formnovalidate>Сгенерировать ИИ</button>
                    <?php endif; ?>
                </div>
                <textarea class="form-control<?= $fieldClass('description') ?>" id="product-description" name="description" rows="8" maxlength="20000" aria-describedby="product-description-help"><?= e((string) ($values['description'] ?? '')) ?></textarea>
                <?php if (isset($errors['description'])): ?>
                    <div class="invalid-feedback"><?= e($errors['description']) ?></div>
                <?php endif; ?>
                <?php if ($descriptionSuggested): ?>
                    <div class="form-text text-info" id="product-description-help"><span class="badge bg-info-transparent">Предложено ИИ</span> Прочитайте и поправьте текст, затем нажмите «Сохранить». Пока вы не сохранили, в карточке Товара прежнее описание.</div>
                <?php elseif ($showDescriptionAi): ?>
                    <div class="form-text" id="product-description-help">ИИ напишет описание по названию, Категории, бренду и Характеристикам — текст подставится сюда, но не сохранится сам. Прежнее описание заменится только после «Сохранить».</div>
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
<?php if ($showDescriptionAi): ?>
    <form id="description-ai-form" method="post" action="/admin/products/<?= (int) $product['id'] ?>/description/ai">
        <?= csrfField() ?>
    </form>
<?php endif; ?>

<?php if ($showCatalogBlocks): ?>
    <?php
    $productId = (int) $product['id'];
    $newState = $variantForm !== null && $variantForm['target'] === 'new' ? $variantForm : null;
    $newValues = $newState['values'] ?? [];
    $newErrors = $newState['errors'] ?? [];
    $newField = static fn (string $key): string => isset($newErrors[$key]) ? ' is-invalid' : '';
    ?>
    <section class="card mb-4" id="variants" aria-labelledby="variants-title">
        <div class="card-header">
            <h2 class="card-title" id="variants-title">Варианты</h2>
        </div>
        <div class="card-body">
            <p class="text-muted">Цена вводится один раз при создании Варианта (имитация импорта из МойСклад) и дальше не меняется. Остаток правится на странице «Склад». Здесь можно изменить скидочную цену, вес/вкус и отключить Вариант.</p>

            <?php if ($variants === []): ?>
                <p class="mb-4">Вариантов пока нет — без них Товар не появится на витрине.</p>
            <?php endif; ?>

            <?php foreach ($variants as $variant): ?>
                <?php
                $variantId = (int) $variant['id'];
                $state = $variantForm !== null && $variantForm['target'] === $variantId ? $variantForm : null;
                $stateValues = $state['values'] ?? [];
                $stateErrors = $state['errors'] ?? [];
                $variantField = static fn (string $key): string => isset($stateErrors[$key]) ? ' is-invalid' : '';
                $discountValue = $stateValues['discount_price'] ?? ($variant['discount_price'] ?? '');
                $variantActive = $state !== null ? (bool) ($stateValues['is_active'] ?? false) : (int) $variant['is_active'] === 1;
                $inStock = (int) $variant['stock_quantity'] - (int) $variant['reserved_quantity'] > 0;
                ?>
                <form method="post" action="/admin/products/<?= $productId ?>/variants/<?= $variantId ?>" class="border rounded p-3 mb-3" id="variant-<?= $variantId ?>" novalidate>
                    <?= csrfField() ?>
                    <div class="row g-3 align-items-end">
                        <div class="col-6 col-md-3">
                            <span class="form-label d-block">Артикул</span>
                            <strong><?= e((string) $variant['sku']) ?></strong>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="form-label d-block">Цена</span>
                            <strong><?= e((string) $variant['price']) ?> ₽</strong>
                        </div>
                        <div class="col-12 col-md-3">
                            <span class="form-label d-block">Наличие</span>
                            <span class="badge <?= $inStock ? 'bg-success-transparent' : 'bg-danger-transparent' ?>"><?= $inStock ? 'В наличии' : 'Нет в наличии' ?></span>
                        </div>
                        <div class="col-12 col-md-3">
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" role="switch" id="variant-active-<?= $variantId ?>" name="is_active" value="1"<?= $variantActive ? ' checked' : '' ?>>
                                <label class="form-check-label" for="variant-active-<?= $variantId ?>">Активен</label>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="variant-discount-<?= $variantId ?>" class="form-label">Скидочная цена</label>
                            <input type="text" inputmode="decimal" class="form-control<?= $variantField('discount_price') ?>" id="variant-discount-<?= $variantId ?>" name="discount_price" maxlength="14" autocomplete="off" aria-describedby="variant-discount-help-<?= $variantId ?>" value="<?= e((string) $discountValue) ?>">
                            <?php if (isset($stateErrors['discount_price'])): ?>
                                <div class="invalid-feedback"><?= e($stateErrors['discount_price']) ?></div>
                            <?php endif; ?>
                            <div class="form-text" id="variant-discount-help-<?= $variantId ?>">Ниже обычной цены. Пусто — без скидки.</div>
                        </div>
                        <?php foreach ($variantAttributes as $attrName): ?>
                            <?php $attrValue = $stateValues['attributes'][$attrName] ?? ($variant['attributes'][$attrName] ?? ''); ?>
                            <div class="col-6 col-md-3">
                                <label for="variant-<?= $variantId ?>-<?= e($attrName) ?>" class="form-label"><?= e($attributeLabel($attrName)) ?></label>
                                <input type="text" class="form-control<?= $variantField('attributes') ?>" id="variant-<?= $variantId ?>-<?= e($attrName) ?>" name="attributes[<?= e($attrName) ?>]" maxlength="<?= $attributeMaxLength ?>" autocomplete="off" value="<?= e((string) $attrValue) ?>">
                            </div>
                        <?php endforeach; ?>
                        <?php if (isset($stateErrors['attributes'])): ?>
                            <div class="col-12"><div class="text-danger fs-12" role="alert"><?= e($stateErrors['attributes']) ?></div></div>
                        <?php endif; ?>
                        <div class="col-12 col-md-2">
                            <button type="submit" class="btn btn-outline-primary w-100">Сохранить</button>
                        </div>
                    </div>
                </form>
            <?php endforeach; ?>

            <form method="post" action="/admin/products/<?= $productId ?>/variants" class="border rounded p-3" id="variant-new" novalidate>
                <?= csrfField() ?>
                <h3 class="h6 mb-3">Добавить Вариант</h3>
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label for="variant-new-sku" class="form-label">Артикул</label>
                        <input type="text" class="form-control<?= $newField('sku') ?>" id="variant-new-sku" name="sku" maxlength="64" autocomplete="off" required value="<?= e((string) ($newValues['sku'] ?? '')) ?>">
                        <?php if (isset($newErrors['sku'])): ?>
                            <div class="invalid-feedback"><?= e($newErrors['sku']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-6 col-md-4">
                        <label for="variant-new-price" class="form-label">Цена, ₽</label>
                        <input type="text" inputmode="decimal" class="form-control<?= $newField('price') ?>" id="variant-new-price" name="price" maxlength="14" autocomplete="off" required aria-describedby="variant-new-price-help" value="<?= e((string) ($newValues['price'] ?? '')) ?>">
                        <?php if (isset($newErrors['price'])): ?>
                            <div class="invalid-feedback"><?= e($newErrors['price']) ?></div>
                        <?php endif; ?>
                        <div class="form-text" id="variant-new-price-help">Дальше не редактируется.</div>
                    </div>
                    <div class="col-6 col-md-4">
                        <label for="variant-new-stock" class="form-label">Начальный остаток</label>
                        <input type="number" class="form-control<?= $newField('stock_quantity') ?>" id="variant-new-stock" name="stock_quantity" min="0" max="<?= $stockMax ?>" step="1" value="<?= e((string) ($newValues['stock_quantity'] ?? '0')) ?>">
                        <?php if (isset($newErrors['stock_quantity'])): ?>
                            <div class="invalid-feedback"><?= e($newErrors['stock_quantity']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php foreach ($variantAttributes as $attrName): ?>
                        <div class="col-6 col-md-4">
                            <label for="variant-new-<?= e($attrName) ?>" class="form-label"><?= e($attributeLabel($attrName)) ?></label>
                            <input type="text" class="form-control<?= $newField('attributes') ?>" id="variant-new-<?= e($attrName) ?>" name="attributes[<?= e($attrName) ?>]" maxlength="<?= $attributeMaxLength ?>" autocomplete="off" value="<?= e((string) ($newValues['attributes'][$attrName] ?? '')) ?>">
                        </div>
                    <?php endforeach; ?>
                    <div class="col-12 col-md-4">
                        <label for="variant-new-discount" class="form-label">Скидочная цена</label>
                        <input type="text" inputmode="decimal" class="form-control<?= $newField('discount_price') ?>" id="variant-new-discount" name="discount_price" maxlength="14" autocomplete="off" value="<?= e((string) ($newValues['discount_price'] ?? '')) ?>">
                        <?php if (isset($newErrors['discount_price'])): ?>
                            <div class="invalid-feedback"><?= e($newErrors['discount_price']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if (isset($newErrors['attributes'])): ?>
                        <div class="col-12"><div class="text-danger fs-12" role="alert"><?= e($newErrors['attributes']) ?></div></div>
                    <?php endif; ?>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Добавить Вариант</button>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <section class="card mb-4" id="characteristics" aria-labelledby="characteristics-title">
        <div class="card-header">
            <h2 class="card-title" id="characteristics-title">Характеристики</h2>
        </div>
        <div class="card-body">
            <p class="text-muted">По этим значениям работает фильтр каталога. Подсказки — значения, уже встречающиеся в каталоге; можно ввести и новое. Пустое поле или «Удалить» убирает Характеристику.</p>

            <form method="post" action="/admin/products/<?= $productId ?>/attributes/ai" class="mb-3">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-outline-primary">Разобрать ИИ</button>
                <span class="form-text ms-2">ИИ прочитает описание Товара и предложит значения. Ничего не сохранится, пока вы сами не нажмёте «Сохранить Характеристики».</span>
            </form>

            <form method="post" action="/admin/products/<?= $productId ?>/attributes" novalidate>
                <?= csrfField() ?>
                <div class="row g-3">
                    <?php foreach ($attributeNames as $attrName): ?>
                        <?php
                        $current = (string) ($attributes[$attrName] ?? '');
                        $suggested = $suggestions[$attrName] ?? null;
                        $fieldValue = $suggested ?? $current;
                        ?>
                        <datalist id="attribute-list-<?= e($attrName) ?>">
                            <?php foreach ($dictionary[$attrName] ?? [] as $known): ?>
                                <option value="<?= e($known) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <div class="col-12 col-md-4">
                            <label for="attribute-<?= e($attrName) ?>" class="form-label"><?= e($attributeLabel($attrName)) ?></label>
                            <input type="text" class="form-control" id="attribute-<?= e($attrName) ?>" name="attributes[<?= e($attrName) ?>]" maxlength="<?= $attributeMaxLength ?>" list="attribute-list-<?= e($attrName) ?>" autocomplete="off" value="<?= e($fieldValue) ?>">
                            <?php if ($suggested !== null): ?>
                                <span class="badge bg-info-transparent mt-1">Предложено ИИ<?= $current !== '' && $current !== $suggested ? ' (было: ' . e($current) . ')' : '' ?></span>
                            <?php endif; ?>
                            <?php if ($current !== ''): ?>
                                <button type="submit" class="btn btn-link btn-sm text-danger p-0 mt-1 d-block" name="delete" value="<?= e($attrName) ?>" formnovalidate>Удалить</button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Сохранить Характеристики</button>
                    </div>
                </div>
            </form>
        </div>
    </section>
<?php endif; ?>
<script type="module" src="/admin/js/product-form.js"></script>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
