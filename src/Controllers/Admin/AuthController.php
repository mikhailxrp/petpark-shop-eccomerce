<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

/**
 * Вход персонала — /admin/login (phase-1.md, Таск 7, FR-AUTH-004).
 * Только роли specialist/shift_admin/content_editor/owner; Покупатель
 * входит через отдельный /login (Таск 6).
 */
final class AuthController
{
    // Одинаковое сообщение для неверного пароля, несуществующего email
    // и роли customer — иначе перебором можно узнать, какие email
    // зарегистрированы как персонал (php.md).
    private const LOGIN_ERROR = 'Неверный email или пароль.';

    public function showLogin(): void
    {
        redirectIfAuthenticated();

        render('admin/login', [
            'error' => getFlash('error'),
        ]);
    }

    public function login(): void
    {
        requireCsrf();

        $email    = trim((string) input('email'));
        $password = (string) input('password');

        if (tooManyAttempts('admin-login', 5, 60)) {
            logWarning('Вход персонала: превышен лимит попыток', ['email' => $email]);
            setFlash('error', self::LOGIN_ERROR);
            redirect('/admin/login');
        }

        $user = $email !== '' ? userFindByEmail($email) : null;

        if (
            $user === null
            || $user['role'] === 'customer'
            || (int) $user['is_active'] !== 1
            || !password_verify($password, $user['password_hash'])
        ) {
            hitRateLimit('admin-login');
            logWarning('Вход персонала: неудачная попытка', ['email' => $email]);
            setFlash('error', self::LOGIN_ERROR);
            redirect('/admin/login');
        }

        clearRateLimit('admin-login');
        regenerateSession();

        $_SESSION['user_id']   = (int) $user['id'];
        $_SESSION['user_role'] = $user['role'];

        redirect(homePathForRole($user['role']));
    }

    public function logout(): void
    {
        requireCsrf();
        ensureSessionStarted();

        $_SESSION = [];
        session_destroy();

        redirect('/admin/login');
    }
}
