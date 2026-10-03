<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

/**
 * Свой публичный профиль Специалиста — /specialist/profile (phase-8.md,
 * Таск 7). Правится имя, должность, биография (простой текст) и фото.
 * Строка находится по `users.id` из сессии: id из запроса не читается,
 * поэтому чужой профиль изменить нельзя. Все POST — CSRF + redirect().
 */
final class SpecialistProfileController
{
    private const ROLE = 'specialist';
    private const FORM_URL = '/specialist/profile';
    private const FORM_FLASH = 'specialist_profile_form';
    private const UPLOAD_PREFIX = 'specialists';
    private const PLACEHOLDER_PHOTO = '/assets/img/team-placeholder.svg';

    private const NAME_MAX = 100;
    private const POSITION_MAX = 120;
    private const BIO_MAX = 2000;

    public function form(): void
    {
        requireRole(self::ROLE);

        $profile = specialistFindOwnProfile((int) $_SESSION['user_id']);
        if ($profile === null) {
            $this->notFound();
            return;
        }

        $form = $this->takeForm();
        $photoPath = $profile['photo_path'];

        render('admin/specialist-profile', [
            'pageTitle'     => 'Мой профиль — PetPark',
            'roleLabel'     => adminRoleLabel(self::ROLE),
            'homeUrl'       => homePathForRole(self::ROLE),
            'userRole'      => self::ROLE,
            'values'        => $form['values'] ?? [
                'name'     => $profile['name'],
                'position' => $profile['position'] ?? '',
                'bio'      => $profile['bio'] ?? '',
            ],
            'errors'        => $form['errors'] ?? [],
            'photoUrl'      => $photoPath !== null && $photoPath !== ''
                ? '/uploads/' . ltrim($photoPath, '/')
                : self::PLACEHOLDER_PHOTO,
            'publicUrl'     => $profile['slug'] === null ? null : '/team/' . $profile['slug'],
            'nameLimit'     => self::NAME_MAX,
            'positionLimit' => self::POSITION_MAX,
            'bioLimit'      => self::BIO_MAX,
            'success'       => getFlash('success'),
            'error'         => getFlash('error'),
        ]);
    }

    public function update(): void
    {
        requireRole(self::ROLE);
        requireCsrf();

        $userId = (int) $_SESSION['user_id'];
        $profile = specialistFindOwnProfile($userId);
        if ($profile === null) {
            $this->notFound();
            return;
        }

        $values = [
            'name'     => trim(mb_scrub((string) input('name', ''))),
            'position' => trim(mb_scrub((string) input('position', ''))),
            'bio'      => trim(mb_scrub((string) input('bio', ''))),
        ];

        $errors = [];
        if ($values['name'] === '' || mb_strlen($values['name']) > self::NAME_MAX) {
            $errors['name'] = 'Укажите имя — не длиннее ' . self::NAME_MAX . ' символов.';
        }
        if (mb_strlen($values['position']) > self::POSITION_MAX) {
            $errors['position'] = 'Должность — не длиннее ' . self::POSITION_MAX . ' символов.';
        }
        if (mb_strlen($values['bio']) > self::BIO_MAX) {
            $errors['bio'] = 'Биография — не длиннее ' . self::BIO_MAX . ' символов.';
        }

        // Ошибка текста — файл на диск не пишем.
        if ($errors !== []) {
            $this->rememberForm($values, $errors);
            redirect(self::FORM_URL);
        }

        $newPhoto = null;
        $files = fileUploadNormalize(is_array($_FILES['photo'] ?? null) ? $_FILES['photo'] : []);
        if ($files !== []) {
            $upload = fileUploadSaveImages($files, $this->uploadDir(), self::UPLOAD_PREFIX, 0, 1);
            if (!$upload['ok']) {
                $this->rememberForm($values, ['photo' => $upload['error']]);
                redirect(self::FORM_URL);
            }
            $newPhoto = $upload['paths'][0];
        }

        try {
            specialistUpdateProfile(
                $userId,
                $values['name'],
                $values['position'] === '' ? null : $values['position'],
                $values['bio'] === '' ? null : $values['bio'],
                $newPhoto
            );
        } catch (\Throwable $e) {
            if ($newPhoto !== null) {
                fileUploadDelete([$newPhoto], $this->uploadDir(), self::UPLOAD_PREFIX);
            }
            logError('Не удалось сохранить профиль Специалиста', ['user_id' => $userId, 'error' => $e->getMessage()]);
            $this->rememberForm($values, []);
            setFlash('error', 'Не удалось сохранить профиль. Попробуйте позже.');
            redirect(self::FORM_URL);
        }

        // Старый файл удаляем только после успешной записи нового пути в БД.
        if ($newPhoto !== null && $profile['photo_path'] !== null) {
            fileUploadDelete([$profile['photo_path']], $this->uploadDir(), self::UPLOAD_PREFIX);
        }

        setFlash('success', 'Профиль сохранён — изменения уже видны на сайте.');
        redirect(self::FORM_URL);
    }

    private function uploadDir(): string
    {
        return ROOT_PATH . '/public/uploads/' . self::UPLOAD_PREFIX;
    }

    private function notFound(): void
    {
        http_response_code(404);
        render('errors/404');
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
