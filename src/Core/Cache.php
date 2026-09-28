<?php

declare(strict_types=1);

/**
 * Файловый кеш в storage/cache/ — без Redis/Memcached, подходит для
 * shared-хостинга. Тот же паттерн файлового хранилища, что
 * storage/cache/rate-limit/ (rateLimitStoragePath(), Core/functions.php),
 * но хранит произвольный текст по TTL, а не JSON-счётчик — для
 * генерируемых ответов вроде sitemap.xml.
 */

function cacheDirPath(string $namespace): string
{
    $dir = ROOT_PATH . '/storage/cache/' . $namespace;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException("Не удалось создать директорию: {$dir}");
    }
    return $dir;
}

function cachePath(string $namespace, string $key): string
{
    return cacheDirPath($namespace) . '/' . sha1($key) . '.cache';
}

/**
 * Возвращает закешированное содержимое, если файл существует и не
 * старше $ttlSeconds — иначе null (кеш-промах или устаревшая запись).
 */
function cacheGet(string $namespace, string $key, int $ttlSeconds): ?string
{
    $path = cachePath($namespace, $key);
    if (!is_file($path) || time() - (int) filemtime($path) > $ttlSeconds) {
        return null;
    }

    $content = file_get_contents($path);

    return $content !== false ? $content : null;
}

function cachePut(string $namespace, string $key, string $content): void
{
    file_put_contents(cachePath($namespace, $key), $content, LOCK_EX);
}
