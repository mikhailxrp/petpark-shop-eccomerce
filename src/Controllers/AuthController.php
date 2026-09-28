<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Вход Покупателя и восстановление пароля — /login, /logout,
 * /forgot-password (phase-1.md, Таск 6, FR-AUTH-001/002/003).
 * Персонал входит через отдельный /admin/login (Таск 7) — здесь
 * пропускается только role === 'customer'.
 */
final class AuthController
{
    // Одинаковое сообщение для неверного пароля и несуществующего email —
    // иначе перебором можно узнать, какие email зарегистрированы (php.md).
    private const LOGIN_ERROR = 'Неверный email или пароль.';

    private const FORGOT_PASSWORD_MESSAGE =
        'Если такой email зарегистрирован у нас, на него отправлен новый пароль.';

    public function showLogin(): void
    {
        redirectIfAuthenticated();

        render('auth/login', [
            'error' => getFlash('error'),
        ]);
    }

    public function login(): void
    {
        requireCsrf();

        $email    = trim((string) input('email'));
        $password = (string) input('password');

        if (tooManyAttempts('login', 5, 60)) {
            logWarning('Вход: превышен лимит попыток', ['email' => $email]);
            setFlash('error', self::LOGIN_ERROR);
            redirect('/login');
        }

        $user = $email !== '' ? userFindByEmail($email) : null;

        if (
            $user === null
            || $user['role'] !== 'customer'
            || !password_verify($password, $user['password_hash'])
        ) {
            hitRateLimit('login');
            logWarning('Вход: неудачная попытка', ['email' => $email]);
            setFlash('error', self::LOGIN_ERROR);
            redirect('/login');
        }

        clearRateLimit('login');
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

        redirect('/');
    }

    public function showForgotPassword(): void
    {
        redirectIfAuthenticated();

        render('auth/forgot-password', [
            'success' => getFlash('success'),
        ]);
    }

    public function forgotPassword(): void
    {
        requireCsrf();

        $email = trim((string) input('email'));

        // Форма шлёт реальные письма — тот же rate-limit, что у /login,
        // защищает от массовой рассылки чужих паролей (не только от
        // перебора, dod-global.md требует его явно только для login/
        // чекаута, но здесь тот же риск — реальная отправка email).
        if (!tooManyAttempts('forgot-password', 5, 60)) {
            hitRateLimit('forgot-password');

            $user = $email !== '' ? userFindByEmail($email) : null;

            if ($user !== null && $user['role'] === 'customer') {
                $newPassword = generatePassword();
                userUpdatePassword((int) $user['id'], password_hash($newPassword, PASSWORD_DEFAULT));

                try {
                    sendPasswordResetEmail($user['email'], $user['name'], $newPassword);
                } catch (\Throwable $e) {
                    // Пользователь всё равно видит общий «успех» ниже —
                    // ошибку SMTP не показываем (php.md: friendly message
                    // юзеру, полный трейс — в лог).
                    logException($e, ['email' => $user['email']]);
                }
            }
        } else {
            logWarning('Восстановление пароля: превышен лимит попыток', ['email' => $email]);
        }

        // Один и тот же ответ независимо от того, найден email, отправлено
        // письмо или сработал rate-limit — иначе ответ раскрывает, кто
        // зарегистрирован (php.md).
        setFlash('success', self::FORGOT_PASSWORD_MESSAGE);
        redirect('/forgot-password');
    }
}
