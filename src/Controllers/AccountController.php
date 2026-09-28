<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Личный кабинет Покупателя — заглушка-приёмник после входа
 * (phase-1.md, Таск 6: "/account в этой фазе только заглушка-приёмник
 * после входа, содержимое — Фаза 7"). Без этого маршрута /login не
 * имеет куда редиректить успешный вход.
 */
final class AccountController
{
    public function index(): void
    {
        requireRole('customer');

        render('account/dashboard', []);
    }
}
