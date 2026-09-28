<?php

declare(strict_types=1);

/**
 * Каркас админки (Valex) — общая шапка/сайдбар для всех страниц
 * персонала. Сайдбар — «Дашборд» + «Отзывы» (Таск 8, только
 * `shift_admin`/`owner` — `content_editor`/`specialist` не модерируют,
 * admin-assembly.md); остальные разделы появятся в более поздних
 * тасках/фазах.
 *
 * @var string $pageTitle
 * @var string $content   Готовый HTML блока контента (собран через ob_start() во View)
 * @var string $roleLabel Человекочитаемое название роли — adminRoleLabel()
 * @var string $homeUrl   /admin или /specialist, в зависимости от роли — homePathForRole()
 * @var string $userRole  Сырая роль из $_SESSION['user_role'] — для пунктов меню, видимых не всем ролям
 */
?>
<!DOCTYPE html>
<html lang="ru" data-nav-layout="vertical" data-theme-mode="light" data-header-styles="light" data-menu-styles="light" data-toggled="close">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">

    <link href="/admin/assets/libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="/admin/assets/css/styles.min.css" rel="stylesheet">
    <link href="/admin/assets/css/icons.min.css" rel="stylesheet">
    <link href="/admin/css/petpark.css" rel="stylesheet">
</head>
<body>
    <div class="page">

        <header class="app-header">
            <div class="main-header-container container-fluid">
                <div class="header-content-left">
                    <div class="header-element">
                        <div class="horizontal-logo">
                            <a href="<?= e($homeUrl) ?>" class="header-logo">
                                <img src="/assets/img/logo.png" alt="PetPark" class="desktop-logo">
                            </a>
                        </div>
                    </div>
                    <div class="header-element">
                        <button type="button" class="sidemenu-toggle header-link animated-arrow hor-toggle horizontal-navtoggle" id="admin-sidebar-toggle" aria-label="Показать/скрыть меню">
                            <i class="fe fe-align-left header-icon"></i>
                        </button>
                    </div>
                </div>
                <div class="header-content-right">
                    <div class="header-element">
                        <span class="fw-semibold"><?= e($roleLabel) ?></span>
                    </div>
                    <div class="header-element">
                        <form method="post" action="/admin/logout">
                            <?= csrfField() ?>
                            <button type="submit" class="btn btn-sm btn-light">Выйти</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <aside class="app-sidebar sticky" id="admin-sidebar">
            <div class="main-sidebar-header">
                <a href="<?= e($homeUrl) ?>" class="header-logo">
                    <img src="/assets/img/logo.png" alt="PetPark" class="desktop-logo">
                </a>
            </div>
            <div class="main-sidebar">
                <?php
                // Активный пункт — по текущему URL, а не захардкожен на
                // «Дашборд»: до появления второго пункта меню (Таск 8)
                // разницы не было видно, сайдбар всегда открывался на
                // /admin или /specialist.
                $currentPath = requestPath();
                ?>
                <nav class="main-menu-container nav nav-pills flex-column">
                    <ul class="main-menu">
                        <li class="slide__category"><span class="category-name"><?= e($roleLabel) ?></span></li>
                        <li class="slide<?= $currentPath === $homeUrl ? ' active' : '' ?>">
                            <a href="<?= e($homeUrl) ?>" class="side-menu__item<?= $currentPath === $homeUrl ? ' active' : '' ?>">
                                <i class="fe fe-home side-menu__icon"></i>
                                <span class="side-menu__label">Дашборд</span>
                            </a>
                        </li>
                        <?php if (in_array($userRole, ['shift_admin', 'owner'], true)): ?>
                            <li class="slide<?= $currentPath === '/admin/reviews' ? ' active' : '' ?>">
                                <a href="/admin/reviews" class="side-menu__item<?= $currentPath === '/admin/reviews' ? ' active' : '' ?>">
                                    <i class="fe fe-star side-menu__icon"></i>
                                    <span class="side-menu__label">Отзывы</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </aside>

        <div class="main-content app-content">
            <div class="container-fluid">
                <?= $content ?>
            </div>
        </div>

        <footer class="footer mt-auto py-3 bg-white text-center">
            <div class="container">
                <span class="text-muted">© <?= date('Y') ?> PetPark</span>
            </div>
        </footer>

    </div>

    <script src="/admin/js/admin-layout.js"></script>
</body>
</html>
