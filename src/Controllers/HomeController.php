<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Главная страница.
 * Заглушка Фазы 0 — Таск 3 заменит src/Views/home.php на полноценный
 * layout Patte, сигнатура контроллера не изменится.
 */
final class HomeController
{
    public function index(): void
    {
        render('home');
    }
}
