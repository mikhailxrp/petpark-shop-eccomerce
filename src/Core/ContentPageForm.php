<?php

declare(strict_types=1);

/**
 * Валидация формы редактора статических страниц `/admin/pages` (phase-8.md,
 * Таск 9). Чистая функция без БД и HTTP — покрыта
 * tests/Unit/ContentPageFormTest.php. Текст проходит санитайзер при
 * сохранении (контентный HTML, `ContentHtml.php`), а не только при выводе.
 */

const CONTENT_PAGE_TITLE_MAX = 200;
const CONTENT_PAGE_SEO_TITLE_MAX = 70;
const CONTENT_PAGE_SEO_DESCRIPTION_MAX = 160;
// TEXT = 65 535 байт; 15 000 символов даже при 4 байтах на символ не переполняют колонку.
const CONTENT_PAGE_BODY_MAX = 15000;

// Страницы, у которых View выводит галерею (`content_page_images`) — только там блок фото в админке.
const CONTENT_PAGE_GALLERY_SLUGS = ['about'];
// Публичная /about показывает не больше 7 фото (ContentController::GALLERY_LIMIT) — лишние не видны.
const CONTENT_PAGE_IMAGES_MAX = 7;

const CONTENT_PAGE_ERROR_REQUIRED = 'Заполните это поле.';

function contentPageHasGallery(string $slug): bool
{
    return in_array($slug, CONTENT_PAGE_GALLERY_SLUGS, true);
}

/**
 * @param array<string, mixed> $input title, body, seo_title, seo_description (сырой ввод)
 * @return array{
 *     values: array{title: string, body: string, seo_title: string, seo_description: string},
 *     errors: array<string, string>
 * } values — очищенные значения (body уже прошёл санитайзер); errors — по полю, пусто = форма верна
 */
function contentPageFormValidate(array $input): array
{
    $values = [
        'title'           => contentPageFormSingleLine($input['title'] ?? ''),
        'body'            => contentHtmlSanitize(trim(mb_scrub(is_string($input['body'] ?? null) ? $input['body'] : ''))),
        'seo_title'       => contentPageFormSingleLine($input['seo_title'] ?? ''),
        'seo_description' => contentPageFormSingleLine($input['seo_description'] ?? ''),
    ];
    $errors = [];

    if ($values['title'] === '') {
        $errors['title'] = CONTENT_PAGE_ERROR_REQUIRED;
    } elseif (mb_strlen($values['title']) > CONTENT_PAGE_TITLE_MAX) {
        $errors['title'] = 'Заголовок — не длиннее ' . CONTENT_PAGE_TITLE_MAX . ' символов.';
    }

    if (trim(strip_tags($values['body'])) === '') {
        $errors['body'] = 'Добавьте текст страницы (разрешены теги p, h2, h3, ul, ol, li, strong, em, a).';
    } elseif (mb_strlen($values['body']) > CONTENT_PAGE_BODY_MAX) {
        $errors['body'] = 'Текст — не длиннее ' . CONTENT_PAGE_BODY_MAX . ' символов.';
    }

    if (mb_strlen($values['seo_title']) > CONTENT_PAGE_SEO_TITLE_MAX) {
        $errors['seo_title'] = 'SEO-заголовок — не длиннее ' . CONTENT_PAGE_SEO_TITLE_MAX . ' символов.';
    }
    if (mb_strlen($values['seo_description']) > CONTENT_PAGE_SEO_DESCRIPTION_MAX) {
        $errors['seo_description'] = 'SEO-описание — не длиннее ' . CONTENT_PAGE_SEO_DESCRIPTION_MAX . ' символов.';
    }

    return ['values' => $values, 'errors' => $errors];
}

function contentPageFormSingleLine(mixed $raw): string
{
    $value = is_string($raw) ? mb_scrub($raw) : '';

    return trim((string) preg_replace('/\s+/u', ' ', $value));
}
