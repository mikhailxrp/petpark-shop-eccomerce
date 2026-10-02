<?php

declare(strict_types=1);

/**
 * Чистая логика формы Товара в админке (phase-7.md, Таск 8; FR-ADM-001):
 * `slug`, валидация полей, отбор путей фото, которые можно удалять с диска.
 * Без БД и HTTP — покрыто unit-тестами; SQL — в src/Models/Product.php.
 */

const PRODUCT_NAME_MAX = 200;
const PRODUCT_SLUG_MAX = 220;
const PRODUCT_DESCRIPTION_MAX = 20000;

const PRODUCT_PHOTOS_MAX = 8;
const PRODUCT_PHOTO_MAX_BYTES = 5 * 1024 * 1024;

// Каталог загрузок относительно public/uploads/; пути в БД — `products/<random>.<ext>`.
const PRODUCT_UPLOAD_PREFIX = 'products';

const PRODUCT_SLUG_TRANSLIT = [
    'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
    'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
    'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
    'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
    'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
];

/**
 * `slug` из названия: транслит кириллицы, всё кроме латиницы и цифр — дефис.
 * Пустая строка, если в названии нет ни одного пригодного символа.
 */
function productSlugify(string $name): string
{
    $slug = strtr(mb_strtolower($name), PRODUCT_SLUG_TRANSLIT);
    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $slug), '-');
    $slug = rtrim(substr($slug, 0, PRODUCT_SLUG_MAX), '-');

    return $slug;
}

function productSlugIsValid(string $slug): bool
{
    return strlen($slug) <= PRODUCT_SLUG_MAX
        && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1;
}

/**
 * Проверка полей формы Товара. Фрилансер (`$full = false`) правит только
 * название и описание: категории, бренд и `slug` из его POST не читаются,
 * даже если пришли.
 *
 * @param array<string, mixed> $input сырой POST
 * @param string $currentSlug `slug` правящегося Товара; пустое поле его сохраняет, а не пересоздаёт (URL не меняется молча)
 * @param list<int> $categoryIds существующие категории
 * @param list<int> $brandIds существующие бренды
 * @return array{0: array<string, mixed>, 1: array<string, string>} [значения, ошибки по полям]
 */
function productFormValidate(array $input, bool $full, string $currentSlug, array $categoryIds, array $brandIds): array
{
    $text = static fn (string $key): string => is_string($input[$key] ?? null)
        ? trim(mb_scrub($input[$key]))
        : '';

    $values = [
        'name'        => $text('name'),
        'description' => $text('description'),
    ];
    $errors = [];

    if ($values['name'] === '') {
        $errors['name'] = 'Укажите название.';
    } elseif (mb_strlen($values['name']) > PRODUCT_NAME_MAX) {
        $errors['name'] = 'Название — не длиннее ' . PRODUCT_NAME_MAX . ' символов.';
    }

    if (mb_strlen($values['description']) > PRODUCT_DESCRIPTION_MAX) {
        $errors['description'] = 'Описание — не длиннее ' . PRODUCT_DESCRIPTION_MAX . ' символов.';
    }

    if (!$full) {
        return [$values, $errors];
    }

    $values['slug'] = strtolower($text('slug'));
    if ($values['slug'] === '') {
        $values['slug'] = $currentSlug !== '' ? $currentSlug : productSlugify($values['name']);
        if ($values['slug'] === '' && !isset($errors['name'])) {
            $errors['slug'] = 'Из названия не получилось собрать адрес — введите его латиницей.';
        }
    } elseif (!productSlugIsValid($values['slug'])) {
        $errors['slug'] = 'Адрес — латиница, цифры и дефисы, например korm-dlya-koshek.';
    }

    $values['category_id'] = 0;
    $categoryRaw = $text('category_id');
    if (ctype_digit($categoryRaw) && in_array((int) $categoryRaw, $categoryIds, true)) {
        $values['category_id'] = (int) $categoryRaw;
    } else {
        $errors['category_id'] = 'Выберите основную категорию.';
    }

    // Вторая категория одна (PK product_secondary_categories = product_id);
    // массив в поле — попытка передать больше.
    $values['secondary_category_id'] = 0;
    if (is_array($input['secondary_category_id'] ?? null)) {
        $errors['secondary_category_id'] = 'Можно выбрать не больше двух категорий: основную и одну дополнительную.';
    } else {
        $secondaryRaw = $text('secondary_category_id');
        if ($secondaryRaw !== '') {
            if (!ctype_digit($secondaryRaw) || !in_array((int) $secondaryRaw, $categoryIds, true)) {
                $errors['secondary_category_id'] = 'Выберите дополнительную категорию из списка.';
            } elseif ((int) $secondaryRaw === $values['category_id']) {
                $errors['secondary_category_id'] = 'Дополнительная категория должна отличаться от основной.';
            } else {
                $values['secondary_category_id'] = (int) $secondaryRaw;
            }
        }
    }

    $values['brand_id'] = 0;
    $brandRaw = $text('brand_id');
    if ($brandRaw !== '') {
        if (ctype_digit($brandRaw) && in_array((int) $brandRaw, $brandIds, true)) {
            $values['brand_id'] = (int) $brandRaw;
        } else {
            $errors['brand_id'] = 'Выберите бренд из списка.';
        }
    }

    return [$values, $errors];
}

/**
 * Пути, которые можно удалить с диска: только файлы, загруженные формой Товара
 * (`products/<32 hex>.<ext>`). Демо-фото из `products/demo/` и любые другие
 * пути остаются нетронутыми.
 *
 * @param list<string> $paths пути из product_images.path
 * @return list<string>
 */
function productUploadedPaths(array $paths): array
{
    return array_values(array_filter(
        $paths,
        static fn (string $path): bool => preg_match(
            '#^' . preg_quote(PRODUCT_UPLOAD_PREFIX, '#') . '/[0-9a-f]{32}\.(?:jpg|png|webp)$#',
            $path
        ) === 1
    ));
}
