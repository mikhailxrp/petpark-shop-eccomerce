<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\MoySklad;

/**
 * Товары в админке — список /admin/products (phase-7.md, Таск 7) и форма
 * создания/правки (Таск 8; FR-ADM-001). Список и правку видят `owner` и
 * `content_editor` (Фрилансер): другие роли `requireRole()` отправляет на их
 * домашнюю страницу. Создавать и деактивировать Товар может только Владелец;
 * Фрилансер правит название, описание и фото — остальные поля из его POST
 * не читаются (`productFormValidate()` с `$full = false`).
 *
 * Таск 9 (FR-ADM-002, FR-DISC-001): Варианты и Характеристики на странице
 * правки — отдельные POST только для Владельца. Цена Варианта вводится один
 * раз, начальный остаток уходит через заглушку МойСклад.
 */
final class ProductController
{
    private const PER_PAGE = 20;
    private const SEARCH_MAX_LENGTH = 64;
    private const PAGE_MAX_DIGITS = 9;
    private const STATUSES = ['active', 'inactive'];

    private const FORM_FLASH = 'product_form';
    private const IMAGE_ORDER_MAX = 9999;
    private const SITEMAP_CACHE_NAMESPACE = 'sitemap';
    private const SITEMAP_CACHE_KEY = 'sitemap.xml';

    private const SAVE_FAILED_ERROR = 'Не удалось сохранить Товар. Попробуйте ещё раз.';
    private const SLUG_TAKEN_ERROR = 'Такой адрес уже занят другим Товаром.';

    private const VARIANT_FLASH = 'variant_form';
    private const VARIANT_NEW = 'new';
    private const SKU_TAKEN_ERROR = 'Вариант с таким артикулом уже есть.';
    private const VARIANT_SAVE_FAILED_ERROR = 'Не удалось сохранить Вариант. Попробуйте ещё раз.';
    private const ATTRIBUTES_SAVE_FAILED_ERROR = 'Не удалось сохранить Характеристики. Попробуйте ещё раз.';

    public function index(): void
    {
        requireRole('owner', 'content_editor');

        $queryInput = input('q', '');
        $query = is_string($queryInput) ? mb_substr(trim($queryInput), 0, self::SEARCH_MAX_LENGTH) : '';

        $categories = categoryAll();
        $categoryInput = input('category', '');
        $categoryId = is_string($categoryInput) && ctype_digit($categoryInput) && strlen($categoryInput) <= self::PAGE_MAX_DIGITS
            ? (int) $categoryInput
            : 0;
        $knownCategoryIds = array_map(static fn (array $row): int => (int) $row['id'], $categories);
        if (!in_array($categoryId, $knownCategoryIds, true)) {
            $categoryId = 0;
        }

        $statusInput = input('status', '');
        $status = is_string($statusInput) && in_array($statusInput, self::STATUSES, true) ? $statusInput : '';

        $total = productAdminCount($query, $categoryId, $status);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        $pageInput = input('page', '1');
        $page = is_string($pageInput) && ctype_digit($pageInput) && strlen($pageInput) <= self::PAGE_MAX_DIGITS
            ? min(max(1, (int) $pageInput), $totalPages)
            : 1;

        $role = (string) $_SESSION['user_role'];

        render('admin/products', [
            'pageTitle'  => 'Товары — PetPark',
            'roleLabel'  => adminRoleLabel($role),
            'homeUrl'    => homePathForRole($role),
            'userRole'   => $role,
            'products'   => productAdminList($query, $categoryId, $status, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'categories' => $categories,
            'query'      => $query,
            'categoryId' => $categoryId,
            'status'     => $status,
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
            'success'    => getFlash('success'),
            'error'      => getFlash('error'),
        ]);
    }

    public function createForm(): void
    {
        requireRole('owner');

        $form = $this->takeForm();

        $this->renderForm(null, $form['values'] ?? [
            'name'                  => '',
            'description'           => '',
            'slug'                  => '',
            'category_id'           => 0,
            'secondary_category_id' => 0,
            'brand_id'              => 0,
        ], $form['errors'] ?? [], []);
    }

    public function store(): void
    {
        requireRole('owner');
        requireCsrf();

        $this->save(null);
    }

    public function editForm(string $id): void
    {
        requireRole('owner', 'content_editor');

        $product = $this->findOr404($id);
        $form = $this->takeForm();

        $this->renderForm(
            $product,
            $form['values'] ?? $product,
            $form['errors'] ?? [],
            productAdminImages((int) $product['id'])
        );
    }

    /** Новый Вариант Товара (FR-ADM-002): цена вводится здесь один раз, остаток — через заглушку МойСклад. */
    public function variantStore(string $id): void
    {
        requireRole('owner');
        requireCsrf();

        $product = $this->findOr404($id);
        $productId = (int) $product['id'];
        $backUrl = '/admin/products/' . $productId . '/edit#variants';

        [$values, $errors] = productVariantValidate($_POST, null);
        if (!isset($errors['sku']) && productVariantSkuTaken($values['sku'])) {
            $errors['sku'] = self::SKU_TAKEN_ERROR;
        }

        if ($errors !== []) {
            $this->rememberVariantForm(self::VARIANT_NEW, $this->variantFormRaw(true), $errors);
            redirect($backUrl);
        }

        try {
            $variantId = productVariantCreate($productId, $values);
        } catch (\Throwable $e) {
            // Гонка двух форм с одним артикулом: проверка выше её не ловит, ловит UNIQUE.
            if ($e instanceof \PDOException && $e->getCode() === '23000' && productVariantSkuTaken($values['sku'])) {
                $this->rememberVariantForm(self::VARIANT_NEW, $this->variantFormRaw(true), ['sku' => self::SKU_TAKEN_ERROR]);
            } else {
                logException($e, ['product_id' => $productId]);
                setFlash('error', self::VARIANT_SAVE_FAILED_ERROR);
                $this->rememberVariantForm(self::VARIANT_NEW, $this->variantFormRaw(true), []);
            }
            redirect($backUrl);
        }

        $stockFailed = false;
        if ($values['stock_quantity'] > 0) {
            try {
                (new MoySklad())->syncVariant($variantId, $values['stock_quantity']);
            } catch (\Throwable $e) {
                logException($e, ['product_id' => $productId, 'variant_id' => $variantId]);
                $stockFailed = true;
            }
        }

        logWarning('Товары: добавлен Вариант', [
            'user_id'    => (int) $_SESSION['user_id'],
            'product_id' => $productId,
            'variant_id' => $variantId,
        ]);

        if ($stockFailed) {
            setFlash('error', 'Вариант создан, но остаток не записан — задайте его на странице «Склад».');
        } else {
            setFlash('success', 'Вариант добавлен.');
        }
        redirect($backUrl);
    }

    /** Правка существующего Варианта: скидка, активность, вес/вкус. Цена, артикул и остаток не принимаются. */
    public function variantUpdate(string $id, string $variantId): void
    {
        requireRole('owner');
        requireCsrf();

        $product = $this->findOr404($id);
        $productId = (int) $product['id'];
        $variant = ctype_digit($variantId) && strlen($variantId) <= self::PAGE_MAX_DIGITS
            ? productVariantFind($productId, (int) $variantId)
            : null;

        if ($variant === null) {
            http_response_code(404);
            render('errors/404');
            return;
        }

        $backUrl = '/admin/products/' . $productId . '/edit#variant-' . (int) $variant['id'];

        [$values, $errors] = productVariantValidate($_POST, (string) $variant['price']);

        if ($errors !== []) {
            $this->rememberVariantForm((int) $variant['id'], $this->variantFormRaw(false), $errors);
            redirect($backUrl);
        }

        try {
            productVariantUpdate($productId, (int) $variant['id'], $values);
        } catch (\Throwable $e) {
            logException($e, ['product_id' => $productId, 'variant_id' => (int) $variant['id']]);
            setFlash('error', self::VARIANT_SAVE_FAILED_ERROR);
            $this->rememberVariantForm((int) $variant['id'], $this->variantFormRaw(false), []);
            redirect($backUrl);
        }

        logWarning('Товары: изменён Вариант', [
            'user_id'    => (int) $_SESSION['user_id'],
            'product_id' => $productId,
            'variant_id' => (int) $variant['id'],
        ]);

        setFlash('success', 'Вариант ' . $variant['sku'] . ' сохранён.');
        redirect($backUrl);
    }

    /** Ручной ввод Характеристик из справочника (FR-ADM-002). Значение вне справочника — отказ целиком. */
    public function attributesSave(string $id): void
    {
        requireRole('owner');
        requireCsrf();

        $product = $this->findOr404($id);
        $productId = (int) $product['id'];
        $backUrl = '/admin/products/' . $productId . '/edit#characteristics';

        $dictionary = attributeDictionary(ATTRIBUTE_EXTRACT_NAMES);
        $input = is_array($_POST['attributes'] ?? null) ? $_POST['attributes'] : [];

        $attributes = [];
        foreach (ATTRIBUTE_EXTRACT_NAMES as $name) {
            if (!is_string($input[$name] ?? null)) {
                continue;
            }
            $value = trim($input[$name]);
            if ($value !== '' && !in_array($value, $dictionary[$name], true)) {
                setFlash('error', 'Значения «' . mb_substr($value, 0, ATTRIBUTE_VALUE_MAX_LENGTH) . '» нет в справочнике — выберите из списка.');
                redirect($backUrl);
            }
            $attributes[$name] = $value;
        }

        try {
            productAttributesSave($productId, $attributes);
        } catch (\Throwable $e) {
            logException($e, ['product_id' => $productId]);
            setFlash('error', self::ATTRIBUTES_SAVE_FAILED_ERROR);
            redirect($backUrl);
        }

        logWarning('Товары: изменены Характеристики', [
            'user_id'    => (int) $_SESSION['user_id'],
            'product_id' => $productId,
        ]);

        setFlash('success', 'Характеристики сохранены.');
        redirect($backUrl);
    }

    /**
     * Решение по ИИ-черновику Характеристики прямо из формы Товара: та же логика,
     * что на экране Фазы 5 (`attributeDecisionResolve()` + `attributeDraftDecide()`).
     * Черновик должен принадлежать этому Товару.
     */
    public function attributeDecide(string $id): void
    {
        requireRole('owner');
        requireCsrf();

        $product = $this->findOr404($id);
        $productId = (int) $product['id'];
        $backUrl = '/admin/products/' . $productId . '/edit#characteristics';

        $draftIdInput = input('draft_id', '');
        $draftId = is_string($draftIdInput) && ctype_digit($draftIdInput) && strlen($draftIdInput) <= self::PAGE_MAX_DIGITS
            ? (int) $draftIdInput
            : 0;

        $ownDraftIds = array_map(
            static fn (array $draft): int => (int) $draft['id'],
            attributeDraftsOpenForProducts([$productId])
        );
        if (!in_array($draftId, $ownDraftIds, true)) {
            setFlash('error', 'Черновик уже обработан или не найден.');
            redirect($backUrl);
        }

        $actionInput = input('action', '');
        $action = is_string($actionInput) ? $actionInput : '';
        $valueInput = input('value', '');

        $draftValue = $action === ATTRIBUTE_ACTION_CONFIRM ? attributeDraftOpenValue($draftId) : null;
        $value = is_string($valueInput) ? $valueInput : '';
        $decision = mb_check_encoding($value, 'UTF-8')
            ? attributeDecisionResolve($action, $draftValue, $value)
            : ['error' => 'Значение содержит недопустимые символы.', 'value' => null, 'outcome' => null];

        if ($decision['error'] !== null) {
            setFlash('error', $decision['error']);
            redirect($backUrl);
        }

        try {
            $applied = attributeDraftDecide($draftId, $decision['value'], (string) $decision['outcome']);
        } catch (\Throwable $e) {
            logError('Решение по черновику Характеристики не записано', ['draft_id' => $draftId, 'error' => $e->getMessage()]);
            setFlash('error', 'Не удалось сохранить решение. Попробуйте ещё раз.');
            redirect($backUrl);
        }

        if ($applied) {
            setFlash('success', match ($decision['outcome']) {
                'rejected' => 'Черновик отклонён.',
                default    => 'Характеристика сохранена — Товар найдётся в фильтре каталога.',
            });
        } else {
            setFlash('error', 'Черновик уже обработан.');
        }

        redirect($backUrl);
    }

    public function update(string $id): void
    {
        requireRole('owner', 'content_editor');
        requireCsrf();

        $this->save($this->findOr404($id));
    }

    public function toggleActive(string $id): void
    {
        requireRole('owner');
        requireCsrf();

        $product = $this->findOr404($id);
        $activate = (int) $product['is_active'] === 0;
        productSetActive((int) $product['id'], $activate);
        $this->forgetSitemap();

        logWarning('Товары: смена статуса', [
            'user_id'    => (int) $_SESSION['user_id'],
            'product_id' => (int) $product['id'],
            'is_active'  => $activate,
        ]);

        setFlash('success', $activate
            ? 'Товар снова активен: ' . $product['name'] . '.'
            : 'Товар деактивирован: ' . $product['name'] . '.');
        redirect('/admin/products/' . (int) $product['id'] . '/edit');
    }

    /**
     * Общая часть store()/update(): проверка полей и фото, запись, откат файлов
     * при сбое БД. `$product` — правящийся Товар или null для нового.
     *
     * @param array<string, mixed>|null $product
     */
    private function save(?array $product): void
    {
        $isOwner = $_SESSION['user_role'] === 'owner';
        $productId = $product === null ? null : (int) $product['id'];
        $formUrl = $productId === null ? '/admin/products/new' : '/admin/products/' . $productId . '/edit';

        [$values, $errors] = productFormValidate(
            $_POST,
            $isOwner,
            (string) ($product['slug'] ?? ''),
            array_map(static fn (array $row): int => (int) $row['id'], categoryAll()),
            array_map(static fn (array $row): int => (int) $row['id'], brandAll())
        );

        if ($isOwner && !isset($errors['slug']) && productSlugTaken($values['slug'], $productId ?? 0)) {
            $errors['slug'] = self::SLUG_TAKEN_ERROR;
        }

        $existingImages = $productId === null ? [] : productAdminImages($productId);
        $removeIds = $this->intList(input('remove_images', []));
        $removedCount = count(array_filter(
            $existingImages,
            static fn (array $image): bool => in_array((int) $image['id'], $removeIds, true)
        ));

        $files = fileUploadNormalize(is_array($_FILES['photos'] ?? null) ? $_FILES['photos'] : []);
        if (count($existingImages) - $removedCount + count($files) > PRODUCT_PHOTOS_MAX) {
            $errors['photos'] = 'У Товара может быть не больше ' . PRODUCT_PHOTOS_MAX . ' фото.';
        }

        $upload = ['ok' => true, 'paths' => []];
        if ($errors === []) {
            $upload = fileUploadSaveImages(
                $files,
                $this->uploadDir(),
                PRODUCT_UPLOAD_PREFIX,
                0,
                PRODUCT_PHOTOS_MAX,
                PRODUCT_PHOTO_MAX_BYTES
            );
            if (!$upload['ok']) {
                $errors['photos'] = $upload['error'];
            }
        }

        if ($errors !== []) {
            $this->rememberForm($values, $errors);
            redirect($formUrl);
        }

        try {
            $result = productAdminSave(
                $productId,
                $isOwner,
                $values,
                $removeIds,
                $this->imageOrder(input('image_order', [])),
                $upload['paths'],
                $this->mainChoice(input('main_image', ''))
            );
        } catch (\Throwable $e) {
            fileUploadDelete($upload['paths'], $this->uploadDir(), PRODUCT_UPLOAD_PREFIX);

            // Гонка двух сохранений с одним slug: проверка выше её не ловит, ловит UNIQUE.
            if ($isOwner && $e instanceof \PDOException && $e->getCode() === '23000'
                && productSlugTaken($values['slug'], $productId ?? 0)
            ) {
                $this->rememberForm($values, ['slug' => self::SLUG_TAKEN_ERROR]);
            } else {
                logException($e, ['product_id' => $productId]);
                setFlash('error', self::SAVE_FAILED_ERROR);
                $this->rememberForm($values, []);
            }
            redirect($formUrl);
        }

        fileUploadDelete(
            productUploadedPaths($result['removed_paths']),
            $this->uploadDir(),
            PRODUCT_UPLOAD_PREFIX
        );
        $this->forgetSitemap();

        logWarning($productId === null ? 'Товары: создан' : 'Товары: изменён', [
            'user_id'    => (int) $_SESSION['user_id'],
            'product_id' => $result['id'],
        ]);

        setFlash('success', $productId === null
            ? 'Товар создан. Чтобы он появился на витрине, добавьте Варианты.'
            : 'Изменения сохранены.');
        redirect('/admin/products/' . $result['id'] . '/edit');
    }

    /**
     * @param array<string, mixed>|null $product null — новый Товар
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     * @param array<int, array<string, mixed>> $images
     */
    private function renderForm(?array $product, array $values, array $errors, array $images): void
    {
        $role = (string) $_SESSION['user_role'];

        // Варианты и Характеристики — только Владельцу и только у существующего Товара.
        $catalog = $product !== null && $role === 'owner'
            ? [
                'variants'     => productAdminVariants((int) $product['id']),
                'attributes'   => productConfirmedAttributes((int) $product['id']),
                'dictionary'   => attributeDictionary(ATTRIBUTE_EXTRACT_NAMES),
                'drafts'       => attributeDraftsOpenForProducts([(int) $product['id']]),
                'variantForm'  => $this->takeVariantForm(),
            ]
            : ['variants' => null, 'attributes' => [], 'dictionary' => [], 'drafts' => [], 'variantForm' => null];

        render('admin/product-form', [
            'pageTitle'  => ($product === null ? 'Новый товар' : 'Правка товара') . ' — PetPark',
            'roleLabel'  => adminRoleLabel($role),
            'homeUrl'    => homePathForRole($role),
            'userRole'   => $role,
            'product'    => $product,
            'values'     => $values,
            'errors'     => $errors,
            'images'     => $images,
            'categories' => categoryAll(),
            'brands'     => brandAll(),
            'photosMax'  => PRODUCT_PHOTOS_MAX,
            'photoMb'    => intdiv(PRODUCT_PHOTO_MAX_BYTES, 1024 * 1024),
            'success'    => getFlash('success'),
            'error'      => getFlash('error'),
            'attributeNames'     => ATTRIBUTE_EXTRACT_NAMES,
            'variantAttributes'  => PRODUCT_VARIANT_ATTRIBUTE_NAMES,
            'attributeMaxLength' => ATTRIBUTE_VALUE_MAX_LENGTH,
            'stockMax'           => PRODUCT_VARIANT_STOCK_MAX,
        ] + $catalog);
    }

    /**
     * Введённое в форме Варианта как строки — чтобы после ошибки показать то,
     * что набрал Владелец, а не проверенные значения.
     *
     * @return array<string, mixed>
     */
    private function variantFormRaw(bool $isNew): array
    {
        $text = static fn (string $key): string => is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
        $attributes = [];
        foreach (PRODUCT_VARIANT_ATTRIBUTE_NAMES as $name) {
            $value = is_array($_POST['attributes'] ?? null) ? ($_POST['attributes'][$name] ?? '') : '';
            $attributes[$name] = is_string($value) ? trim($value) : '';
        }

        $raw = ['discount_price' => $text('discount_price'), 'attributes' => $attributes];

        return $isNew
            ? $raw + ['sku' => $text('sku'), 'price' => $text('price'), 'stock_quantity' => $text('stock_quantity')]
            : $raw + ['is_active' => ($_POST['is_active'] ?? '') === '1'];
    }

    /**
     * @param int|string $target id Варианта или VARIANT_NEW
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     */
    private function rememberVariantForm(int|string $target, array $values, array $errors): void
    {
        setFlash(
            self::VARIANT_FLASH,
            json_encode(['target' => $target, 'values' => $values, 'errors' => $errors], JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @return array{target: int|string, values: array<string, mixed>, errors: array<string, string>}|null
     */
    private function takeVariantForm(): ?array
    {
        $raw = getFlash(self::VARIANT_FLASH);
        if ($raw === null) {
            return null;
        }

        $form = json_decode($raw, true);

        return is_array($form) && isset($form['target']) && is_array($form['values'] ?? null) && is_array($form['errors'] ?? null)
            ? $form
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function findOr404(string $id): array
    {
        $product = ctype_digit($id) && strlen($id) <= self::PAGE_MAX_DIGITS ? productAdminFind((int) $id) : null;

        if ($product === null) {
            http_response_code(404);
            render('errors/404');
            exit;
        }

        return $product;
    }

    /**
     * @return list<int>
     */
    private function intList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $item) {
            if (is_string($item) && ctype_digit($item) && strlen($item) <= self::PAGE_MAX_DIGITS) {
                $ids[(int) $item] = (int) $item;
            }
        }

        return array_values($ids);
    }

    /**
     * @return array<int, int> id фото → порядок
     */
    private function imageOrder(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $order = [];
        foreach ($raw as $imageId => $position) {
            if (is_string($position) && ctype_digit($position) && strlen($position) <= self::PAGE_MAX_DIGITS) {
                $order[(int) $imageId] = min((int) $position, self::IMAGE_ORDER_MAX);
            }
        }

        return $order;
    }

    /**
     * `main_image`: id существующего фото или `new:N` — N-е из загружаемых.
     *
     * @return array{existing?: int, new?: int}|null
     */
    private function mainChoice(mixed $raw): ?array
    {
        if (!is_string($raw)) {
            return null;
        }
        if (ctype_digit($raw) && strlen($raw) <= self::PAGE_MAX_DIGITS) {
            return ['existing' => (int) $raw];
        }
        if (preg_match('/^new:(\d{1,2})$/', $raw, $match) === 1) {
            return ['new' => (int) $match[1]];
        }

        return null;
    }

    private function uploadDir(): string
    {
        return ROOT_PATH . '/public/uploads/' . PRODUCT_UPLOAD_PREFIX;
    }

    // Sitemap кешируется на час, а изменение каталога должно отражаться сразу
    // (seo.md): иначе деактивированный Товар остаётся в нём до конца TTL.
    private function forgetSitemap(): void
    {
        $file = cachePath(self::SITEMAP_CACHE_NAMESPACE, self::SITEMAP_CACHE_KEY);
        if (is_file($file)) {
            unlink($file);
        }
    }

    /**
     * Ошибки и введённое переживают redirect через сессию (POST → redirect).
     *
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     */
    private function rememberForm(array $values, array $errors): void
    {
        setFlash(self::FORM_FLASH, json_encode(['values' => $values, 'errors' => $errors], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{values: array<string, mixed>, errors: array<string, string>}|null
     */
    private function takeForm(): ?array
    {
        $raw = getFlash(self::FORM_FLASH);
        if ($raw === null) {
            return null;
        }

        $form = json_decode($raw, true);

        return is_array($form) && is_array($form['values'] ?? null) && is_array($form['errors'] ?? null)
            ? $form
            : null;
    }
}
