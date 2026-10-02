<?php

declare(strict_types=1);

/**
 * Загрузка изображений (фото Возврата, FR-RET-001). Тип определяется по
 * содержимому файла через finfo, а не по имени и не по присланному клиентом
 * Content-Type; имя на диске — случайное. Каталог должен лежать под
 * public/uploads/, где PHP не исполняется (public/uploads/.htaccess).
 */

const FILE_UPLOAD_RANDOM_NAME_BYTES = 16;

/**
 * Привести $_FILES['field'] с multiple к списку по одному файлу.
 * Одиночное поле тоже превращается в список из одного файла; пустой выбор
 * (UPLOAD_ERR_NO_FILE) отбрасывается.
 *
 * @return list<array{tmp_name: string, size: int, error: int}>
 */
function fileUploadNormalize(array $input): array
{
    if (!isset($input['tmp_name'], $input['size'], $input['error'])) {
        return [];
    }

    $tmpNames = (array) $input['tmp_name'];
    $files = [];

    foreach ($tmpNames as $index => $tmpName) {
        $error = (int) (((array) $input['error'])[$index] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $files[] = [
            'tmp_name' => (string) $tmpName,
            'size'     => (int) (((array) $input['size'])[$index] ?? 0),
            'error'    => $error,
        ];
    }

    return $files;
}

/**
 * Проверить и сохранить изображения Возврата в $targetDir.
 *
 * Всё или ничего: при ошибке на любом файле уже сохранённые удаляются.
 *
 * @param list<array{tmp_name: string, size: int, error: int}> $files результат fileUploadNormalize()
 * @param string $targetDir абсолютный путь каталога под public/uploads/
 * @param string $publicPrefix префикс пути, который пишется в БД (относительно public/)
 * @param (callable(string, string): bool)|null $mover перенос файла; по умолчанию — только настоящая HTTP-загрузка (подмена нужна тестам)
 * @return array{ok: true, paths: list<string>}|array{ok: false, error: string}
 */
function fileUploadSaveImages(
    array $files,
    string $targetDir,
    string $publicPrefix,
    int $minFiles = RETURN_PHOTOS_MIN,
    int $maxFiles = RETURN_PHOTOS_MAX,
    int $maxBytes = RETURN_PHOTO_MAX_BYTES,
    ?callable $mover = null
): array {
    if (count($files) < $minFiles) {
        return ['ok' => false, 'error' => 'Добавьте хотя бы одно фото.'];
    }
    if (count($files) > $maxFiles) {
        return ['ok' => false, 'error' => 'Можно прикрепить не больше ' . $maxFiles . ' фото.'];
    }

    $mover ??= static fn (string $from, string $to): bool => is_uploaded_file($from) && move_uploaded_file($from, $to);

    // Сначала проверяем все файлы, и только потом пишем на диск.
    $extensions = [];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    foreach ($files as $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Не удалось загрузить файл. Попробуйте ещё раз.'];
        }
        if ($file['size'] > $maxBytes || $file['size'] <= 0) {
            return ['ok' => false, 'error' => 'Размер каждого фото — не больше ' . intdiv($maxBytes, 1024 * 1024) . ' МБ.'];
        }

        $mime = $finfo->file($file['tmp_name']);
        $extension = RETURN_PHOTO_MIME_EXTENSIONS[$mime === false ? '' : $mime] ?? null;
        if ($extension === null) {
            return ['ok' => false, 'error' => 'Допустимы только фото в форматах JPEG, PNG и WebP.'];
        }

        $extensions[] = $extension;
    }

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        logError('Каталог загрузки недоступен', ['dir' => $targetDir]);
        return ['ok' => false, 'error' => 'Не удалось сохранить фото. Попробуйте позже.'];
    }

    $savedPaths = [];
    foreach ($files as $index => $file) {
        $name = bin2hex(random_bytes(FILE_UPLOAD_RANDOM_NAME_BYTES)) . '.' . $extensions[$index];

        if (!$mover($file['tmp_name'], rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $name)) {
            fileUploadDelete($savedPaths, $targetDir, $publicPrefix);
            logError('Не удалось сохранить загруженный файл', ['dir' => $targetDir]);
            return ['ok' => false, 'error' => 'Не удалось сохранить фото. Попробуйте позже.'];
        }

        $savedPaths[] = rtrim($publicPrefix, '/') . '/' . $name;
    }

    return ['ok' => true, 'paths' => $savedPaths];
}

/**
 * Удалить ранее сохранённые файлы по путям, которые вернул fileUploadSaveImages()
 * (откат, если запись в БД не удалась). Чужие пути не трогает — только файлы
 * внутри $targetDir.
 *
 * @param list<string> $paths
 */
function fileUploadDelete(array $paths, string $targetDir, string $publicPrefix): void
{
    $prefix = rtrim($publicPrefix, '/') . '/';

    foreach ($paths as $path) {
        if (!str_starts_with($path, $prefix)) {
            continue;
        }

        $file = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . basename($path);
        if (is_file($file)) {
            unlink($file);
        }
    }
}
