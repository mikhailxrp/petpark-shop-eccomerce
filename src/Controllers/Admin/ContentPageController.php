<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

/**
 * Редактор статических страниц — /admin/pages (phase-8.md, Таск 9; ADR-008).
 * Только `owner` и `shift_admin`. Slug не правится. Фото (галерея страницы)
 * есть только у страниц, где View её выводит (contentPageHasGallery()).
 * Все POST — CSRF + redirect().
 */
final class ContentPageController
{
    private const ROLES = ['owner', 'shift_admin'];
    private const LIST_URL = '/admin/pages';
    private const FORM_FLASH = 'content_page_form';
    private const ID_MAX_DIGITS = 9;
    private const UPLOAD_PREFIX = 'content-pages';
    private const SITEMAP_CACHE_NAMESPACE = 'sitemap';
    private const SITEMAP_CACHE_KEY = 'sitemap.xml';

    private const SAVE_FAILED_ERROR = 'Не удалось сохранить страницу. Попробуйте ещё раз.';
    private const IMAGE_FAILED_ERROR = 'Не удалось сохранить фото. Попробуйте ещё раз.';

    public function index(): void
    {
        requireRole(...self::ROLES);

        render('admin/pages/index', $this->layoutData('Страницы — PetPark') + [
            'pages'   => contentPageList(),
            'success' => getFlash('success'),
            'error'   => getFlash('error'),
        ]);
    }

    public function editForm(string $id): void
    {
        requireRole(...self::ROLES);

        $page = $this->findOr404($id);
        $form = $this->takeForm();
        $hasGallery = contentPageHasGallery((string) $page['slug']);
        $images = $hasGallery ? contentPageImages((int) $page['id']) : [];

        render('admin/pages/edit', $this->layoutData('Редактирование страницы — PetPark') + [
            'page'              => $page,
            'values'            => $form['values'] ?? [
                'title'           => (string) $page['title'],
                'body'            => (string) $page['body'],
                'seo_title'       => (string) ($page['seo_title'] ?? ''),
                'seo_description' => (string) ($page['seo_description'] ?? ''),
            ],
            'errors'            => $form['errors'] ?? [],
            'publicUrl'         => '/' . $page['slug'],
            'hasGallery'        => $hasGallery,
            'images'            => $images,
            'imagesMax'         => CONTENT_PAGE_IMAGES_MAX,
            'photoMaxMb'        => intdiv(RETURN_PHOTO_MAX_BYTES, 1024 * 1024),
            'titleLimit'        => CONTENT_PAGE_TITLE_MAX,
            'bodyLimit'         => CONTENT_PAGE_BODY_MAX,
            'seoTitleLimit'     => CONTENT_PAGE_SEO_TITLE_MAX,
            'seoDescriptionLimit' => CONTENT_PAGE_SEO_DESCRIPTION_MAX,
            'success'           => getFlash('success'),
            'error'             => getFlash('error'),
        ]);
    }

    public function update(string $id): void
    {
        requireRole(...self::ROLES);
        requireCsrf();

        $page = $this->findOr404($id);
        $formUrl = $this->formUrl($page);

        $result = contentPageFormValidate($_POST);
        if ($result['errors'] !== []) {
            $this->rememberForm($result['values'], $result['errors']);
            redirect($formUrl);
        }

        $values = $result['values'];

        try {
            contentPageUpdate(
                (int) $page['id'],
                $values['title'],
                $values['body'],
                $values['seo_title'] === '' ? null : $values['seo_title'],
                $values['seo_description'] === '' ? null : $values['seo_description']
            );
        } catch (\Throwable $e) {
            logException($e, ['action' => 'content_page_update', 'page_id' => $page['id']]);
            $this->rememberForm($values, []);
            setFlash('error', self::SAVE_FAILED_ERROR);
            redirect($formUrl);
        }

        $this->forgetSitemap();
        setFlash('success', 'Страница сохранена — изменения уже видны на сайте.');
        redirect($formUrl);
    }

    public function imageUpload(string $id): void
    {
        requireRole(...self::ROLES);
        requireCsrf();

        $page = $this->findOr404($id);
        $formUrl = $this->formUrl($page);
        $this->requireGallery($page);

        $files = fileUploadNormalize(is_array($_FILES['photos'] ?? null) ? $_FILES['photos'] : []);
        $free = CONTENT_PAGE_IMAGES_MAX - contentPageImageCount((int) $page['id']);
        if ($free <= 0) {
            setFlash('error', 'На странице уже максимум фото: ' . CONTENT_PAGE_IMAGES_MAX . '.');
            redirect($formUrl);
        }

        $upload = fileUploadSaveImages($files, $this->uploadDir($page), $this->uploadPrefix($page), 1, $free);
        if (!$upload['ok']) {
            setFlash('error', $upload['error']);
            redirect($formUrl);
        }

        try {
            foreach ($upload['paths'] as $path) {
                contentPageImageAdd((int) $page['id'], $path);
            }
        } catch (\Throwable $e) {
            logException($e, ['action' => 'content_page_image_add', 'page_id' => $page['id']]);
            // Строки, уже попавшие в БД, остаются без файла — убираем их вместе с файлами.
            $this->dropImagesByPath((int) $page['id'], $upload['paths']);
            fileUploadDelete($upload['paths'], $this->uploadDir($page), $this->uploadPrefix($page));
            setFlash('error', self::IMAGE_FAILED_ERROR);
            redirect($formUrl);
        }

        setFlash('success', 'Фото добавлены.');
        redirect($formUrl);
    }

    public function imageDelete(string $id, string $imageId): void
    {
        requireRole(...self::ROLES);
        requireCsrf();

        $page = $this->findOr404($id);
        $formUrl = $this->formUrl($page);
        $this->requireGallery($page);

        // Картинка ищется вместе с id страницы: чужой imageId даёт null и ничего не удаляет.
        $image = ctype_digit($imageId) && strlen($imageId) <= self::ID_MAX_DIGITS
            ? contentPageImageFind((int) $imageId, (int) $page['id'])
            : null;
        if ($image === null) {
            http_response_code(404);
            render('errors/404');
            exit;
        }

        try {
            contentPageImageDelete((int) $image['id'], (int) $page['id']);
        } catch (\Throwable $e) {
            logException($e, ['action' => 'content_page_image_delete', 'page_id' => $page['id']]);
            setFlash('error', self::IMAGE_FAILED_ERROR);
            redirect($formUrl);
        }

        // Файл удаляем после строки в БД: сбой тут оставляет лишний файл, а не битую ссылку.
        // Сид кладёт файлы в тот же каталог content-pages/{slug}.
        fileUploadDelete([(string) $image['path']], $this->uploadDir($page), $this->uploadPrefix($page));

        setFlash('success', 'Фото удалено.');
        redirect($formUrl);
    }

    /** @return array<string, string> */
    private function layoutData(string $pageTitle): array
    {
        $role = (string) $_SESSION['user_role'];

        return [
            'pageTitle' => $pageTitle,
            'roleLabel' => adminRoleLabel($role),
            'homeUrl'   => homePathForRole($role),
            'userRole'  => $role,
        ];
    }

    /** @return array<string, mixed> */
    private function findOr404(string $id): array
    {
        $page = ctype_digit($id) && strlen($id) <= self::ID_MAX_DIGITS ? contentPageFindById((int) $id) : null;

        if ($page === null) {
            http_response_code(404);
            render('errors/404');
            exit;
        }

        return $page;
    }

    /** Фото есть только у страниц с галереей — иначе прямой POST отклоняется. */
    private function requireGallery(array $page): void
    {
        if (!contentPageHasGallery((string) $page['slug'])) {
            http_response_code(404);
            render('errors/404');
            exit;
        }
    }

    private function formUrl(array $page): string
    {
        return self::LIST_URL . '/' . (int) $page['id'];
    }

    private function uploadPrefix(array $page): string
    {
        return self::UPLOAD_PREFIX . '/' . $page['slug'];
    }

    private function uploadDir(array $page): string
    {
        return ROOT_PATH . '/public/uploads/' . $this->uploadPrefix($page);
    }

    /** @param list<string> $paths */
    private function dropImagesByPath(int $pageId, array $paths): void
    {
        foreach (contentPageImages($pageId) as $image) {
            if (in_array($image['path'], $paths, true)) {
                contentPageImageDelete($image['id'], $pageId);
            }
        }
    }

    // Sitemap кешируется на час, а правка страницы должна отражаться сразу
    // (seo.md): иначе обновлённая дата остаётся старой до конца TTL.
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
     * @param array<string, string> $values
     * @param array<string, string> $errors
     */
    private function rememberForm(array $values, array $errors): void
    {
        setFlash(self::FORM_FLASH, json_encode(['values' => $values, 'errors' => $errors], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{values: array<string, string>, errors: array<string, string>}|null
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
