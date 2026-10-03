<?php

declare(strict_types=1);

/**
 * @var string      $pageTitle
 * @var string      $pageDescription
 * @var string      $content         Готовый HTML блока контента (собран через ob_start() во View)
 * @var string|null $canonicalUrl    Опционально — <link rel="canonical">, страницы с параметрами фильтра/сортировки/пагинации (seo.md)
 * @var bool        $robotsNoindex   Опционально — noindex, follow вместе с $canonicalUrl
 * @var string      $footerVariant   Опционально — 'two' (по умолчанию, index.html) | 'catalog' (our-products.html)
 */

$canonicalUrl ??= null;
$robotsNoindex ??= false;
$footerVariant ??= 'two';

// Единственное чтение БД в каркасе (dev-log, Таск 7): ссылки на мессенджеры
// нужны кнопке, чату и соц-иконкам шапки/футера на каждой странице. Сбой БД
// не должен ронять страницу — просто без ссылок.
try {
    $messengerLinks = messengerLinks(CHANNELS_ENABLED, siteSettingMessengerUrls(), messengerLabels());
} catch (Throwable $e) {
    logException($e, ['action' => 'messenger_links_load']);
    $messengerLinks = [];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <?php if ($canonicalUrl !== null): ?>
        <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <?php endif; ?>
    <?php if ($robotsNoindex): ?>
        <meta name="robots" content="noindex, follow">
    <?php endif; ?>
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">

    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/owl.carousel.min.css">
    <link rel="stylesheet" href="/assets/css/owl.theme.default.min.css">
    <link rel="stylesheet" href="/assets/css/nice-select.css">
    <link rel="stylesheet" href="/assets/css/jquery.fancybox.min.css">
    <link rel="stylesheet" href="/assets/css/fontawesome.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <link rel="stylesheet" href="/assets/css/color.css">
    <link rel="stylesheet" href="/assets/css/petpark.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/petpark.css') ?>">

    <script src="/assets/js/jquery-3.6.0.min.js"></script>
    <script src="/assets/js/preloader.js"></script>
</head>
<body>
    <div class="preloader">
        <div class="container">
            <div class="dot dot-1"></div>
            <div class="dot dot-2"></div>
            <div class="dot dot-3"></div>
        </div>
    </div>

    <?php include __DIR__ . '/../components/header.php'; ?>

    <main>
        <?= $content ?>
    </main>

    <?php include __DIR__ . '/../components/' . ($footerVariant === 'catalog' ? 'footer-catalog' : 'footer') . '.php'; ?>

    <?php if (!str_starts_with((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/checkout')): ?>
        <?php include __DIR__ . '/../components/chat-widget.php'; ?>
    <?php endif; ?>

    <?php include __DIR__ . '/../components/messenger-button.php'; ?>

    <div id="progress">
        <span id="progress-value"><i class="fa-solid fa-up-long"></i></span>
    </div>

    <script src="/assets/js/bootstrap.min.js"></script>
    <script src="/assets/js/owl.carousel.min.js"></script>
    <script src="/assets/js/jquery.nice-select.min.js"></script>
    <script src="/assets/js/jquery.fancybox.min.js"></script>
    <script src="/assets/js/custom.js"></script>
    <script src="/assets/js/catalog.js"></script>
    <script src="/assets/js/product-variants.js"></script>
    <script src="/assets/js/cart.js"></script>
    <script src="/assets/js/checkout.js"></script>
    <script src="/assets/js/booking.js"></script>
    <script src="/assets/js/password-toggle.js"></script>
    <script src="/assets/js/hero-nav.js"></script>
    <script type="module" src="/assets/js/chat.js"></script>
    <script type="module" src="/assets/js/alerts.js"></script>
</body>
</html>
