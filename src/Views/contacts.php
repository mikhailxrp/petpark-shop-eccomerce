<?php

declare(strict_types=1);

/**
 * Контакты — /contacts (phase-8.md, Таск 2), макет `contact.html` без формы
 * (форма — Таск 3) и без блока наград. Телефон и email — константы
 * config.php (один источник с шапкой и подвалом), адрес и часы — те же, что
 * у самовывоза, ссылки мессенджеров — `$messengerLinks` из layouts/public.php.
 * @var array<string, mixed> $page
 * @var string               $bodyHtml очищенный `body` — вступление
 * @var string               $mapUrl   Ссылка «Проложить маршрут»
 * @var list<array{code: string, label: string, url: string}> $messengerLinks Из Controller (layout считает их позже)
 */

$breadcrumbs = [
    ['name' => 'Главная', 'url' => '/'],
    ['name' => (string) $page['title'], 'url' => null],
];
$bannerTitle = (string) $page['title'];

ob_start();
?>
<?php include __DIR__ . '/components/page-banner.php'; ?>

<section class="gap">
    <div class="container">
        <div class="heading">
            <h6>Мы будем рады вас услышать</h6>
            <h2>Свяжитесь с нами</h2>
            <div class="contacts__intro"><?= $bodyHtml ?></div>
        </div>
        <div class="row">
            <div class="col-lg-4 col-md-6">
                <div class="content-us">
                    <?php $ringSize = 140; $ringFill = '#000'; include __DIR__ . '/components/ring-svg.php'; ?>
                    <i>
                        <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M0,81v350h512V81H0z M456.952,111L256,286.104L55.047,111H456.952z M30,128.967l134.031,116.789L30,379.787V128.967z M51.213,401l135.489-135.489L256,325.896l69.298-60.384L460.787,401H51.213z M482,379.788L347.969,245.756L482,128.967V379.788z"></path>
                        </svg>
                    </i>
                    <span>Email</span>
                    <a href="mailto:<?= e(SHOP_EMAIL) ?>"><?= e(SHOP_EMAIL) ?></a>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="content-us">
                    <?php $ringSize = 140; $ringFill = '#000'; include __DIR__ . '/components/ring-svg.php'; ?>
                    <i>
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="m7 2.75c-.41421 0-.75.33579-.75.75v17c0 .4142.33579.75.75.75h10c.4142 0 .75-.3358.75-.75v-17c0-.41421-.3358-.75-.75-.75zm-2.25.75c0-1.24264 1.00736-2.25 2.25-2.25h10c1.2426 0 2.25 1.00736 2.25 2.25v17c0 1.2426-1.0074 2.25-2.25 2.25h-10c-1.24264 0-2.25-1.0074-2.25-2.25z"></path>
                            <path d="m10.25 5c0-.41421.3358-.75.75-.75h2c.4142 0 .75.33579.75.75s-.3358.75-.75.75h-2c-.4142 0-.75-.33579-.75-.75z"></path>
                            <path d="m9.25 19c0-.4142.33579-.75.75-.75h4c.4142 0 .75.3358.75.75s-.3358.75-.75.75h-4c-.41421 0-.75-.3358-.75-.75z"></path>
                        </svg>
                    </i>
                    <span>Телефон</span>
                    <a href="tel:<?= e(SHOP_PHONE_TEL) ?>"><?= e(SHOP_PHONE) ?></a>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="content-us mb-0">
                    <?php $ringSize = 140; $ringFill = '#000'; include __DIR__ . '/components/ring-svg.php'; ?>
                    <i>
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16zm1-13h-2v6l5 3 1-1.7-4-2.3z"></path>
                        </svg>
                    </i>
                    <span>Часы работы</span>
                    <p class="contacts__hours"><?= e(SHOP_PICKUP_HOURS) ?></p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="gap no-top">
    <div class="container">
        <div class="row">
            <div class="col-lg-6">
                <div class="head-office contacts__card">
                    <div class="d-flex align-items-center">
                        <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                        <h3 class="contacts__subtitle">Адрес магазина</h3>
                    </div>
                    <p><?= e(SHOP_PICKUP_ADDRESS) ?></p>
                    <a class="button" href="<?= e($mapUrl) ?>" target="_blank" rel="noopener noreferrer">Проложить маршрут</a>
                </div>
            </div>
            <?php if ($messengerLinks !== []): ?>
                <div class="col-lg-6">
                    <div class="head-office contacts__card">
                        <div class="d-flex align-items-center">
                            <i class="fa-solid fa-comments" aria-hidden="true"></i>
                            <h3 class="contacts__subtitle">Напишите нам в мессенджере</h3>
                        </div>
                        <?php include __DIR__ . '/components/social-icons.php'; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/layouts/public.php';
