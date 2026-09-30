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
        '/account/pets'        => ['AccountController', 'pets'],
        '/account/pets/{id}/edit' => ['AccountController', 'petEdit'],
        '/admin/login'         => ['Admin\AuthController', 'showLogin'],
        '/admin'               => ['Admin\DashboardController', 'index'],
        '/admin/reviews'       => ['Admin\ReviewController', 'index'],
        '/admin/orders'        => ['Admin\OrderController', 'index'],
        '/admin/orders/new'    => ['Admin\OrderController', 'createForm'],
        '/admin/orders/variants' => ['Admin\OrderController', 'searchVariants'],
        '/admin/orders/{id}'   => ['Admin\OrderController', 'show'],
        '/admin/stock'         => ['Admin\StockController', 'index'],
        '/specialist'         => ['Admin\DashboardController', 'specialist'],
        '/catalog'             => ['CatalogController', 'index'],
        '/catalog/{cat}'       => ['CatalogController', 'category'],
        '/catalog/{cat}/{sub}' => ['CatalogController', 'subcategory'],
        '/search'              => ['SearchController', 'index'],
        '/product/{slug}'      => ['ProductController', 'show'],
        '/cart'                => ['CartController', 'index'],
        '/checkout'            => ['CheckoutController', 'index'],
        '/checkout/success/{id}' => ['CheckoutController', 'success'],
        '/payment/{id}'        => ['PaymentController', 'show'],
        '/payment/{id}/failed' => ['PaymentController', 'failed'],
        '/sitemap.xml'         => ['SitemapController', 'index'],
    ],
    'POST' => [
        '/login'           => ['AuthController', 'login'],
        '/logout'          => ['AuthController', 'logout'],
        '/account/pets'              => ['AccountController', 'petStore'],
        '/account/pets/{id}'         => ['AccountController', 'petUpdate'],
        '/account/pets/{id}/delete'  => ['AccountController', 'petDelete'],
        '/forgot-password' => ['AuthController', 'forgotPassword'],
        '/admin/login'     => ['Admin\AuthController', 'login'],
        '/admin/logout'    => ['Admin\AuthController', 'logout'],
        '/product/{slug}/review'       => ['ReviewController', 'store'],
        '/favorites/toggle'            => ['FavoriteController', 'toggle'],
        '/cart/add'                    => ['CartController', 'add'],
        '/cart/update'                 => ['CartController', 'update'],
        '/cart/remove'                 => ['CartController', 'remove'],
        '/checkout'                    => ['CheckoutController', 'store'],
        '/payment/{id}/callback'       => ['PaymentController', 'callback'],
        '/payment/{id}/cancel'         => ['PaymentController', 'cancel'],
        '/admin/reviews/{id}/publish'  => ['Admin\ReviewController', 'publish'],
        '/admin/reviews/{id}/reject'   => ['Admin\ReviewController', 'reject'],
        '/admin/orders/new'            => ['Admin\OrderController', 'store'],
        '/admin/orders/{id}/status'    => ['Admin\OrderController', 'changeStatus'],
        '/admin/orders/{id}/mark-paid' => ['Admin\OrderController', 'markPaid'],
        '/admin/orders/{id}/items'     => ['Admin\OrderController', 'editItems'],
        '/admin/stock/{id}/sync'       => ['Admin\StockController', 'sync'],
    ],
];
