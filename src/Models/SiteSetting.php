<?php

declare(strict_types=1);

/**
 * Модель настроек сайта (site_settings) — только SQL через PDO, возвращает
 * массивы (php.md). Phase 6, Таск 7: ссылки на мессенджеры (FR-NOTIF-003).
 */

/**
 * Ссылки на мессенджеры: код канала → URL. Коды без строки в БД не попадают
 * в результат.
 *
 * @return array<string, string>
 */
function siteSettingMessengerUrls(): array
{
    $stmt = getPdo()->prepare('
        SELECT setting_key, setting_value
        FROM site_settings
        WHERE setting_key LIKE :prefix
    ');
    $stmt->execute(['prefix' => MESSENGER_SETTING_PREFIX . '%']);

    $urls = [];

    foreach ($stmt->fetchAll() as $row) {
        $code = substr((string) $row['setting_key'], strlen(MESSENGER_SETTING_PREFIX));
        $urls[$code] = (string) $row['setting_value'];
    }

    return $urls;
}

/**
 * Сохранить ссылки на мессенджеры разом (код → URL, пустая строка — скрыть):
 * все строки или ни одной.
 *
 * @param array<string, string> $urls
 */
function siteSettingMessengerUrlsSave(array $urls): void
{
    $pdo = getPdo();
    $stmt = $pdo->prepare('
        INSERT INTO site_settings (setting_key, setting_value)
        VALUES (:key, :value)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ');

    $pdo->beginTransaction();

    try {
        foreach ($urls as $code => $url) {
            $stmt->execute(['key' => MESSENGER_SETTING_PREFIX . $code, 'value' => $url]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();

        throw $e;
    }
}
