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
        '/'      => ['HomeController', 'index'],
        '/login' => ['AuthController', 'showLogin'],
    ],
    'POST' => [
        '/login'  => ['AuthController', 'login'],
        '/logout' => ['AuthController', 'logout'],
    ],
];
