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
    private const CONTACT_FORM = 'contact';
    private const CONTACT_FORM_SESSION_KEY = 'contact_form';
    private const CONTACT_MIN_FILL_SECONDS = 3;
    private const CONTACT_MAX_ATTEMPTS = 5;
    private const CONTACT_DECAY_SECONDS = 60;
    private const CONTACT_SUCCESS = 'Спасибо, заявка принята. Мы свяжемся с вами в ближайшее время.';
    private const CONTACT_RATE_LIMITED = 'Слишком много попыток отправки. Попробуйте через минуту.';
    private const CONTACT_INVALID = 'Проверьте поля формы — все они обязательны.';

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
                'slug' => (string) $specialist['slug'],
                'position' => TeamController::positionLabel($specialist['position'], (string) $specialist['kind']),
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
        // Ошибки и введённые значения живут до первого показа (после redirect из contactStore).
        $form = $_SESSION[self::CONTACT_FORM_SESSION_KEY] ?? null;
        unset($_SESSION[self::CONTACT_FORM_SESSION_KEY]);

        $this->showPage('contacts', 'contacts', [
            'mapUrl' => self::MAP_SEARCH_URL . rawurlencode(SHOP_PICKUP_ADDRESS),
            // Layout собирает ссылки после View, поэтому блоку «Контакты» нужны свои.
            'messengerLinks' => messengerLinks(CHANNELS_ENABLED, siteSettingMessengerUrls(), messengerLabels()),
            'formToken' => generateFormToken(self::CONTACT_FORM),
            'formValues' => $form['values'] ?? ['name' => '', 'phone' => '', 'email' => '', 'message' => ''],
            'formErrors' => $form['errors'] ?? [],
            'formSuccess' => getFlash('contact_success'),
            'formAlert' => getFlash('contact_error'),
        ]);
    }

    /**
     * POST /contacts — обращение с формы. Бот-защита (honeypot, время
     * заполнения, одноразовый токен) по паттерну ReviewController/
     * BookingController: отказ неотличим от успеха, в БД и очередь ничего не
     * пишется (dod-global.md). Rate-limit считает каждую попытку и не
     * сбрасывается после успеха — иначе спамер с валидными данными не упрётся в лимит.
     */
    public function contactStore(): void
    {
        requireCsrf();

        if (tooManyAttempts(self::CONTACT_FORM, self::CONTACT_MAX_ATTEMPTS, self::CONTACT_DECAY_SECONDS)) {
            logWarning('Контакты: превышен лимит отправок');
            setFlash('contact_error', self::CONTACT_RATE_LIMITED);
            redirect('/contacts');
        }
        hitRateLimit(self::CONTACT_FORM);

        $honeypot = trim((string) input('website'));
        $formToken = input('form_token');
        $tokenValid = verifyFormToken(
            self::CONTACT_FORM,
            is_string($formToken) && $formToken !== '' ? $formToken : null,
            self::CONTACT_MIN_FILL_SECONDS
        );
        if ($honeypot !== '' || !$tokenValid) {
            logWarning('Контакты: отклонено как бот', [
                'honeypot_filled' => $honeypot !== '',
                'token_valid'     => $tokenValid,
            ]);
            setFlash('contact_success', self::CONTACT_SUCCESS);
            redirect('/contacts');
        }

        $result = contactFormValidate([
            'name'    => input('name'),
            'phone'   => input('phone'),
            'email'   => input('email'),
            'message' => input('message'),
        ]);

        if ($result['errors'] !== []) {
            $_SESSION[self::CONTACT_FORM_SESSION_KEY] = $result;
            setFlash('contact_error', self::CONTACT_INVALID);
            redirect('/contacts');
        }

        $values = $result['values'];
        $id = contactRequestCreate($values['name'], $values['phone'], $values['email'], $values['message']);

        try {
            notifierEnqueueContactRequest(['id' => $id] + $values);
        } catch (\Throwable $e) {
            // Обращение уже сохранено; сбой очереди не должен превращаться для посетителя в ошибку.
            logException($e);
        }

        setFlash('contact_success', self::CONTACT_SUCCESS);
        redirect('/contacts');
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
