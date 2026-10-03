<?php

declare(strict_types=1);

/**
 * Каркас админки (Valex) — общая шапка/сайдбар для всех страниц
 * персонала. Сайдбар — «Дашборд» + «Отзывы» (Таск 8, только
 * `shift_admin`/`owner` — `content_editor`/`specialist` не модерируют,
 * admin-assembly.md); остальные разделы появятся в более поздних
 * тасках/фазах. «Товары» — `owner` и `content_editor` (phase-7.md,
 * Таск 7); у Фрилансера «Дашборда» нет — его домашняя страница и есть
 * /admin/products.
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
                        <?php if ($userRole !== 'content_editor'): ?>
                            <li class="slide<?= $currentPath === $homeUrl ? ' active' : '' ?>">
                                <a href="<?= e($homeUrl) ?>" class="side-menu__item<?= $currentPath === $homeUrl ? ' active' : '' ?>">
                                    <i class="fe fe-home side-menu__icon"></i>
                                    <span class="side-menu__label">Дашборд</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if (in_array($userRole, ['owner', 'content_editor'], true)): ?>
                            <?php $productsActive = str_starts_with($currentPath, '/admin/products'); ?>
                            <li class="slide<?= $productsActive ? ' active' : '' ?>">
                                <a href="/admin/products" class="side-menu__item<?= $productsActive ? ' active' : '' ?>">
                                    <i class="fe fe-shopping-bag side-menu__icon"></i>
                                    <span class="side-menu__label">Товары</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if (in_array($userRole, ['specialist', 'shift_admin', 'owner'], true)): ?>
                            <?php $clientsActive = str_starts_with($currentPath, '/admin/clients'); ?>
                            <li class="slide<?= $clientsActive ? ' active' : '' ?>">
                                <a href="/admin/clients" class="side-menu__item<?= $clientsActive ? ' active' : '' ?>">
                                    <i class="fe fe-user side-menu__icon"></i>
                                    <span class="side-menu__label">Клиенты</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if ($userRole === 'specialist'): ?>
                            <li class="slide<?= $currentPath === '/specialist/profile' ? ' active' : '' ?>">
                                <a href="/specialist/profile" class="side-menu__item<?= $currentPath === '/specialist/profile' ? ' active' : '' ?>">
                                    <i class="fe fe-user-check side-menu__icon"></i>
                                    <span class="side-menu__label">Мой профиль</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if (in_array($userRole, ['shift_admin', 'owner'], true)): ?>
                            <?php $ordersActive = str_starts_with($currentPath, '/admin/orders'); ?>
                            <li class="slide<?= $ordersActive ? ' active' : '' ?>">
                                <a href="/admin/orders" class="side-menu__item<?= $ordersActive ? ' active' : '' ?>">
                                    <i class="fe fe-shopping-cart side-menu__icon"></i>
                                    <span class="side-menu__label">Заказы</span>
                                </a>
                            </li>
                            <?php $bookingsActive = str_starts_with($currentPath, '/admin/bookings'); ?>
                            <li class="slide<?= $bookingsActive ? ' active' : '' ?>">
                                <a href="/admin/bookings" class="side-menu__item<?= $bookingsActive ? ' active' : '' ?>">
                                    <i class="fe fe-calendar side-menu__icon"></i>
                                    <span class="side-menu__label">Записи</span>
                                </a>
                            </li>
                            <li class="slide<?= $currentPath === '/admin/time-off' ? ' active' : '' ?>">
                                <a href="/admin/time-off" class="side-menu__item<?= $currentPath === '/admin/time-off' ? ' active' : '' ?>">
                                    <i class="fe fe-slash side-menu__icon"></i>
                                    <span class="side-menu__label">Закрытие слотов</span>
                                </a>
                            </li>
                            <?php $stockActive =str_starts_with($currentPath, '/admin/stock'); ?>
                            <li class="slide<?= $stockActive ? ' active' : '' ?>">
                                <a href="/admin/stock" class="side-menu__item<?= $stockActive ? ' active' : '' ?>">
                                    <i class="fe fe-package side-menu__icon"></i>
                                    <span class="side-menu__label">Склад</span>
                                </a>
                            </li>
                            <?php $inboxActive = str_starts_with($currentPath, '/admin/inbox'); ?>
                            <li class="slide<?= $inboxActive ? ' active' : '' ?>">
                                <a href="/admin/inbox" class="side-menu__item<?= $inboxActive ? ' active' : '' ?>">
                                    <i class="fe fe-message-circle side-menu__icon"></i>
                                    <span class="side-menu__label">Обращения</span>
                                </a>
                            </li>
                            <?php
                            // Единственный запрос в каркасе (dev-log, Таск 6): счётчик
                            // новых заявок на Возврат. Сбой БД не должен ронять страницу.
                            try {
                                $newReturns = returnCountByStatus('submitted');
                            } catch (Throwable $e) {
                                $newReturns = 0;
                            }
                            $returnsActive = str_starts_with($currentPath, '/admin/returns');
                            ?>
                            <li class="slide<?= $returnsActive ? ' active' : '' ?>">
                                <a href="/admin/returns" class="side-menu__item<?= $returnsActive ? ' active' : '' ?>">
                                    <i class="fe fe-rotate-ccw side-menu__icon"></i>
                                    <span class="side-menu__label">Возвраты</span>
                                    <?php if ($newReturns > 0): ?>
                                        <span class="badge bg-danger ms-auto" aria-label="Новых заявок: <?= $newReturns ?>"><?= $newReturns ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li class="slide<?= $currentPath === '/admin/reviews' ? ' active' : '' ?>">
                                <a href="/admin/reviews" class="side-menu__item<?= $currentPath === '/admin/reviews' ? ' active' : '' ?>">
                                    <i class="fe fe-star side-menu__icon"></i>
                                    <span class="side-menu__label">Отзывы</span>
                                </a>
                            </li>
                            <?php $pagesActive = str_starts_with($currentPath, '/admin/pages'); ?>
                            <li class="slide<?= $pagesActive ? ' active' : '' ?>">
                                <a href="/admin/pages" class="side-menu__item<?= $pagesActive ? ' active' : '' ?>">
                                    <i class="fe fe-file-text side-menu__icon"></i>
                                    <span class="side-menu__label">Страницы</span>
                                </a>
                            </li>
                            <?php $staffActive = str_starts_with($currentPath, '/admin/staff'); ?>
                            <li class="slide<?= $staffActive ? ' active' : '' ?>">
                                <a href="/admin/staff" class="side-menu__item<?= $staffActive ? ' active' : '' ?>">
                                    <i class="fe fe-users side-menu__icon"></i>
                                    <span class="side-menu__label">Сотрудники</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if ($userRole === 'owner'): ?>
                            <li class="slide<?= $currentPath === '/admin/ai' ? ' active' : '' ?>">
                                <a href="/admin/ai" class="side-menu__item<?= $currentPath === '/admin/ai' ? ' active' : '' ?>">
                                    <i class="fe fe-cpu side-menu__icon"></i>
                                    <span class="side-menu__label">ИИ-помощники</span>
                                </a>
                            </li>
                            <li class="slide<?= $currentPath === '/admin/reports' ? ' active' : '' ?>">
                                <a href="/admin/reports" class="side-menu__item<?= $currentPath === '/admin/reports' ? ' active' : '' ?>">
                                    <i class="fe fe-bar-chart-2 side-menu__icon"></i>
                                    <span class="side-menu__label">Отчёты</span>
                                </a>
                            </li>
                            <li class="slide<?= $currentPath === '/admin/settings/messengers' ? ' active' : '' ?>">
                                <a href="/admin/settings/messengers" class="side-menu__item<?= $currentPath === '/admin/settings/messengers' ? ' active' : '' ?>">
                                    <i class="fe fe-send side-menu__icon"></i>
                                    <span class="side-menu__label">Мессенджеры</span>
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

    <script src="/admin/assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="/admin/js/admin-layout.js"></script>
    <script type="module" src="/assets/js/alerts.js"></script>
</body>
</html>
