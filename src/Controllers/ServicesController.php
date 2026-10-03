<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Публичные страницы Услуг (phase-8.md, Таск 4; FR-CNT-004): список
 * `/services` и детали `/services/{slug}`. Цена и длительность читаются из
 * `services` — тот же источник, что у формы Записи.
 */
final class ServicesController
{
    public function index(): void
    {
        render('services', [
            'pageTitle' => seoTitle('services'),
            'pageDescription' => seoDescription('services'),
            'services' => servicesPublicList(),
        ]);
    }

    public function show(string $slug): void
    {
        $service = serviceFindActiveBySlug($slug);
        if ($service === null) {
            http_response_code(404);
            render('errors/404');
            return;
        }

        render('service-details', [
            'pageTitle' => seoTitle('service', $service),
            'pageDescription' => seoDescription('service', $service),
            'service' => $service,
        ]);
    }
}
