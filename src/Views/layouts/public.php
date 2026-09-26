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
    <link rel="icon" href="/assets/img/heading-img.png">

    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/nice-select.css">
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

    <div id="progress">
        <span id="progress-value"><i class="fa-solid fa-up-long"></i></span>
    </div>

    <script src="/assets/js/jquery.nice-select.min.js"></script>
    <script src="/assets/js/custom.js"></script>
    <script src="/assets/js/catalog.js"></script>
</body>
</html>
