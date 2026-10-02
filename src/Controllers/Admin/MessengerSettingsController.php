<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

/**
 * Ссылки на мессенджеры — /admin/settings/messengers (phase-6.md, Таск 7;
 * FR-NOTIF-003). Только `owner`. Пустое поле — мессенджер скрыт; значение
 * выводится в href, поэтому принимается только https:// (isSafeMessengerUrl()).
 */
final class MessengerSettingsController
{
    private const SAVE_FAILED_ERROR = 'Не удалось сохранить ссылки. Попробуйте ещё раз.';

    public function index(): void
    {
        requireRole('owner');

        $role = (string) $_SESSION['user_role'];
        $old = $_SESSION['messenger_settings_old'] ?? null;
        unset($_SESSION['messenger_settings_old']);

        $urls = is_array($old) ? $old : siteSettingMessengerUrls();

        render('admin/messenger-settings', [
            'pageTitle'   => 'Мессенджеры — PetPark',
            'roleLabel'   => adminRoleLabel($role),
            'homeUrl'     => homePathForRole($role),
            'userRole'    => $role,
            'labels'      => messengerLabels(),
            'urls'        => $urls,
            'enabled'     => enabledChannels(CHANNELS_ENABLED, array_keys(messengerLabels())),
            'maxLength'   => MESSENGER_LINK_MAX_LENGTH,
            'fieldErrors' => $this->takeFieldErrors(),
            'success'     => getFlash('success'),
            'error'       => getFlash('error'),
        ]);
    }

    public function update(): void
    {
        requireRole('owner');
        requireCsrf();

        $urls = [];
        $errors = [];

        foreach (messengerLabels() as $code => $label) {
            $value = input('link_' . $code, '');
            $url = is_string($value) ? trim($value) : '';
            $urls[$code] = $url;

            if ($url !== '' && !isSafeMessengerUrl($url, MESSENGER_LINK_MAX_LENGTH)) {
                $errors[$code] = 'Укажите ссылку целиком, начиная с https:// (до '
                    . MESSENGER_LINK_MAX_LENGTH . ' символов), или оставьте поле пустым.';
            }
        }

        if ($errors !== []) {
            $_SESSION['messenger_settings_old'] = $urls;
            $_SESSION['messenger_settings_errors'] = $errors;
            setFlash('error', 'Ссылки не сохранены: исправьте отмеченные поля.');
            redirect('/admin/settings/messengers');
        }

        try {
            siteSettingMessengerUrlsSave($urls);
            setFlash('success', 'Ссылки сохранены.');
        } catch (\Throwable $e) {
            logException($e, ['action' => 'messenger_settings_save']);
            setFlash('error', self::SAVE_FAILED_ERROR);
        }

        redirect('/admin/settings/messengers');
    }

    /** @return array<string, string> */
    private function takeFieldErrors(): array
    {
        $errors = $_SESSION['messenger_settings_errors'] ?? [];
        unset($_SESSION['messenger_settings_errors']);

        return is_array($errors) ? $errors : [];
    }
}
