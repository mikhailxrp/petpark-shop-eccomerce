<?php

declare(strict_types=1);

/**
 * @var string $pageTitle
 * @var string $pageDescription
 * @var string $content Готовый HTML блока контента (собран через ob_start() во View)
 */
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <link rel="icon" href="/assets/img/heading-img.png">

    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
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

    <?php include __DIR__ . '/../components/footer.php'; ?>

    <div id="progress">
        <span id="progress-value"><i class="fa-solid fa-up-long"></i></span>
    </div>

    <script src="/assets/js/custom.js"></script>
</body>
</html>
