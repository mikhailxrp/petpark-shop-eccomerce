<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Статические страницы (phase-8.md, Таск 1): «О компании» (`/about`) с
 * блоками «История» и «Как мы работаем»; Таск 2 — `/contacts`, `/privacy`,
 * `/offer`. Заголовок и вступление — из
 * `content_pages`; остальные тексты блоков (счётчики, таймлайн, шаги) —
 * черновики исполнителя, не подтверждённые Владельцем (`Q-046`), живут
 * здесь, а не в `body` (planning-log.md, сужение ADR-008).
 */
final class ContentController
{
    private const ABOUT_SLUG = 'about';
    private const REVIEWS_LIMIT = 6;
    private const GALLERY_LIMIT = 7;
    private const MAP_SEARCH_URL = 'https://yandex.ru/maps/?text=';
    private const TEAM_PLACEHOLDER_PHOTO = '/assets/img/team-placeholder.svg';

    public function about(): void
    {
        $page = contentPageFindBySlug(self::ABOUT_SLUG);
        if ($page === null) {
            http_response_code(404);
            render('errors/404');
            return;
        }

        $body = contentHtmlSanitize((string) $page['body']);
        // seoDescription() берёт первые слова body — теги в <meta> не нужны.
        $seoEntity = ['body' => trim((string) preg_replace('/\s+/u', ' ', strip_tags($body)))] + $page;

        $team = array_map(
            static fn (array $specialist): array => [
                'name' => (string) $specialist['name'],
                'position' => $specialist['kind'] === 'vet' ? 'Ветеринарный врач' : 'Грумер',
                'photo' => self::TEAM_PLACEHOLDER_PHOTO,
            ],
            specialistListForPublic()
        );

        $galleryImages = array_slice(contentPageImages((int) $page['id']), 0, self::GALLERY_LIMIT);

        render('content-page', [
            'page' => $page,
            'pageTitle' => seoTitle('content_page', $seoEntity),
            'pageDescription' => seoDescription('content_page', $seoEntity),
            'bodyHtml' => $body,
            'reviews' => reviewPublishedForHome(self::REVIEWS_LIMIT),
            'reviewsAggregate' => reviewPublishedAggregate(),
            'team' => $team,
            'galleryImages' => $galleryImages,
            'stats' => self::aboutStats(),
            'timeline' => self::aboutTimeline(),
            'steps' => self::workSteps(),
        ]);
    }

    public function contacts(): void
    {
        $this->showPage('contacts', 'contacts', [
            'mapUrl' => self::MAP_SEARCH_URL . rawurlencode(SHOP_PICKUP_ADDRESS),
            // Layout собирает ссылки после View, поэтому блоку «Контакты» нужны свои.
            'messengerLinks' => messengerLinks(CHANNELS_ENABLED, siteSettingMessengerUrls(), messengerLabels()),
        ]);
    }

    public function privacy(): void
    {
        $this->showPage('privacy', 'legal-page');
    }

    public function offer(): void
    {
        $this->showPage('offer', 'legal-page');
    }

    /**
     * Страница `content_pages` по slug → View; нет страницы — 404. `body`
     * очищается белым списком (contentHtmlSanitize), SEO — из seoTitle/seoDescription.
     *
     * @param array<string, mixed> $extra Доп. переменные View
     */
    private function showPage(string $slug, string $view, array $extra = []): void
    {
        $page = contentPageFindBySlug($slug);
        if ($page === null) {
            http_response_code(404);
            render('errors/404');
            return;
        }

        $body = contentHtmlSanitize((string) $page['body']);
        // seoDescription() берёт первые слова body — теги в <meta> не нужны;
        // пробел перед тегом, чтобы заголовок не склеивался со следующим абзацем.
        $plainBody = strip_tags(str_replace('<', ' <', $body));
        $seoEntity = ['body' => trim((string) preg_replace('/\s+/u', ' ', $plainBody))] + $page;

        render($view, [
            'page' => $page,
            'pageTitle' => seoTitle('content_page', $seoEntity),
            'pageDescription' => seoDescription('content_page', $seoEntity),
            'bodyHtml' => $body,
        ] + $extra);
    }

    /**
     * Черновые счётчики (`Q-046`): реальных цифр в БД и ТЗ нет.
     *
     * @return array<int, array{number: int, suffix: string, label: string, icon: string}>
     */
    private static function aboutStats(): array
    {
        return [
            ['number' => 500, 'suffix' => '+', 'label' => 'Довольных клиентов', 'icon' => '/assets/img/fun-facts-1.png'],
            ['number' => 98, 'suffix' => '%', 'label' => 'Положительных отзывов', 'icon' => '/assets/img/fun-facts-2.png'],
            ['number' => 2, 'suffix' => 'k', 'label' => 'Заказов доставлено', 'icon' => '/assets/img/fun-facts-3.png'],
            ['number' => 300, 'suffix' => '+', 'label' => 'Товаров в каталоге', 'icon' => '/assets/img/fun-facts-4.png'],
        ];
    }

    /**
     * Черновой таймлайн «История» (`Q-046`): годы и вехи не подтверждены Владельцем.
     *
     * @return array<int, array{year: string, title: string, text: string, modifier: string}>
     */
    private static function aboutTimeline(): array
    {
        return [
            ['year' => '2018', 'title' => 'Первый магазин', 'text' => 'Открыли небольшой зоомагазин в Ростове-на-Дону с кормами и аксессуарами для кошек и собак.', 'modifier' => ''],
            ['year' => '2019', 'title' => 'Расширили ассортимент', 'text' => 'Добавили товары для декоративных птиц, средства гигиены и ветеринарные препараты.', 'modifier' => ''],
            ['year' => '2020', 'title' => 'Доставка по городу', 'text' => 'Запустили доставку и самовывоз: заказ можно оформить, не выходя из дома.', 'modifier' => ''],
            ['year' => '2022', 'title' => 'Центр ухода за питомцами', 'text' => 'Открыли груминг-салон и приём ветеринарных специалистов.', 'modifier' => 'color'],
            ['year' => '2024', 'title' => 'Онлайн-каталог и запись', 'text' => 'Запустили сайт с каталогом, отзывами и записью на груминг и ветконсультации.', 'modifier' => 'color'],
            ['year' => '2025', 'title' => 'Единое окно для клиентов', 'text' => 'Начали отвечать на вопросы клиентов из всех мессенджеров в одном месте.', 'modifier' => 'color'],
        ];
    }

    /**
     * Шаги «Как мы работаем» — по реальному потоку сайта (каталог → заказ/запись → получение).
     *
     * @return array<int, array{number: int, title: string, text: string, icon: string, modifier: string}>
     */
    private static function workSteps(): array
    {
        return [
            ['number' => 1, 'title' => 'Выберите товар или услугу', 'text' => 'Найдите нужное в каталоге или выберите груминг и ветконсультацию, подходящие вашему питомцу.', 'icon' => '/assets/img/works-1.png', 'modifier' => ''],
            ['number' => 2, 'title' => 'Оформите заказ или запись', 'text' => 'Выберите доставку или самовывоз, а для услуги — специалиста и удобное время.', 'icon' => '/assets/img/works-2.png', 'modifier' => 'two'],
            ['number' => 3, 'title' => 'Получите и наслаждайтесь', 'text' => 'Заберите заказ или приходите на приём — мы позаботимся о вашем питомце.', 'icon' => '/assets/img/works-3.png', 'modifier' => ''],
        ];
    }
}
