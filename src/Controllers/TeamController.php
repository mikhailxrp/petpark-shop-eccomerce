<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Публичный профиль Специалиста `/team/{slug}` (phase-8.md, Таск 6).
 * Отдельной страницы `/team` нет — список живёт в блоке «Команда PetPark»
 * на `/about`. Неизвестный slug и отключённый сотрудник — 404.
 */
final class TeamController
{
    private const PLACEHOLDER_PHOTO = '/assets/img/team-placeholder.svg';
    private const UPLOADS_URL_PREFIX = '/uploads/';

    public function show(string $slug): void
    {
        $specialist = specialistFindPublicBySlug($slug);
        if ($specialist === null) {
            http_response_code(404);
            render('errors/404');
            return;
        }

        $specialist['position'] = self::positionLabel($specialist['position'], $specialist['kind']);
        $specialist['photo'] = self::photoUrl($specialist['photo_path']);

        render('team-details', [
            'pageTitle' => seoTitle('specialist', $specialist),
            'pageDescription' => seoDescription('specialist', $specialist),
            'specialist' => $specialist,
        ]);
    }

    /**
     * URL фото из `photo_path` (относительно public/uploads/); нет фото — заглушка.
     */
    public static function photoUrl(?string $photoPath): string
    {
        return $photoPath !== null && $photoPath !== ''
            ? self::UPLOADS_URL_PREFIX . ltrim($photoPath, '/')
            : self::PLACEHOLDER_PHOTO;
    }

    /**
     * Должность из БД; пустая — по виду Услуг (как на `/about`).
     */
    public static function positionLabel(?string $position, string $kind): string
    {
        $position = trim((string) $position);
        if ($position !== '') {
            return $position;
        }

        return $kind === 'vet' ? 'Ветеринарный врач' : 'Грумер';
    }
}
