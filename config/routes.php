<?php

declare(strict_types=1);

/**
 * Все маршруты приложения.
 * Формат: 'МЕТОД' => ['/путь' => ['ИмяКонтроллера', 'метод']]
 */

// Саморегистрации нет — Покупатель появляется только через первый
// Заказ/Запись (FR-AUTH-001 правило 2, Q-027), поэтому /register нет.
return [
    'GET' => [
        '/'                    => ['HomeController', 'index'],
        '/login'               => ['AuthController', 'showLogin'],
        '/forgot-password'     => ['AuthController', 'showForgotPassword'],
        '/account'             => ['AccountController', 'index'],
        '/admin/login'         => ['Admin\AuthController', 'showLogin'],
        '/admin'               => ['Admin\DashboardController', 'index'],
        '/admin/reviews'       => ['Admin\ReviewController', 'index'],
        '/specialist'          => ['Admin\DashboardController', 'specialist'],
        '/catalog'             => ['CatalogController', 'index'],
        '/catalog/{cat}'       => ['CatalogController', 'category'],
        '/catalog/{cat}/{sub}' => ['CatalogController', 'subcategory'],
        '/search'              => ['SearchController', 'index'],
        '/product/{slug}'      => ['ProductController', 'show'],
        '/sitemap.xml'         => ['SitemapController', 'index'],
    ],
    'POST' => [
        '/login'           => ['AuthController', 'login'],
        '/logout'          => ['AuthController', 'logout'],
        '/forgot-password' => ['AuthController', 'forgotPassword'],
        '/admin/login'     => ['Admin\AuthController', 'login'],
        '/admin/logout'    => ['Admin\AuthController', 'logout'],
        '/product/{slug}/review'       => ['ReviewController', 'store'],
        '/favorites/toggle'            => ['FavoriteController', 'toggle'],
        '/admin/reviews/{id}/publish'  => ['Admin\ReviewController', 'publish'],
        '/admin/reviews/{id}/reject'   => ['Admin\ReviewController', 'reject'],
    ],
];
