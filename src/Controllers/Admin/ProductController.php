<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

/**
 * Товары в админке — список /admin/products (phase-7.md, Таск 7) и форма
 * создания/правки (Таск 8; FR-ADM-001). Список и правку видят `owner` и
 * `content_editor` (Фрилансер): другие роли `requireRole()` отправляет на их
 * домашнюю страницу. Создавать и деактивировать Товар может только Владелец;
 * Фрилансер правит название, описание и фото — остальные поля из его POST
 * не читаются (`productFormValidate()` с `$full = false`).
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
        ]);
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
