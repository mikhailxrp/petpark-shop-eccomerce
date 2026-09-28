<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Форма отзыва на Карточке товара — `add-review`, доступна и Гостю, и
 * Покупателю (`FR-CARD-005`, phase-1.md Таск 8). Отзыв всегда уходит в
 * `pending` (`Q-048`) — публикует Администратор смены/Владелец в
 * `/admin/reviews` (Admin\ReviewController).
 *
 * Бот-проверка (honeypot + минимальное время заполнения + одноразовый
 * токен, `generateFormToken()`/`verifyFormToken()`, `functions.php`) —
 * при срабатывании ответ ничем не должен отличаться от настоящего
 * успеха (dod-global.md, «Безопасность»): тот же редирект, то же
 * flash-сообщение, запись в БД не создаётся.
 */
final class ReviewController
{
    private const MIN_FORM_FILL_SECONDS = 3;
    private const SUCCESS_MESSAGE = 'Спасибо! Ваш отзыв отправлен на проверку.';
    private const VALIDATION_ERROR = 'Проверьте поля формы — имя, email, оценка и текст отзыва обязательны.';

    public function store(string $slug): void
    {
        requireCsrf();

        $product = productFindBySlug($slug);
        if ($product === null) {
            http_response_code(404);
            render('errors/404');
            return;
        }

        $productUrl = '/product/' . $slug . '/';

        $honeypot = trim((string) input('website'));
        $token = input('form_token');
        $tokenValid = verifyFormToken(
            'add-review',
            is_string($token) && $token !== '' ? $token : null,
            self::MIN_FORM_FILL_SECONDS
        );

        if ($honeypot !== '' || !$tokenValid) {
            // Отказ бота не должен отличаться от успеха — тот же флеш,
            // тот же редирект; попытка остаётся только в логе.
            logWarning('Отзыв: отклонён как бот', [
                'product_id'      => $product['id'],
                'honeypot_filled' => $honeypot !== '',
                'token_valid'     => $tokenValid,
            ]);
            setFlash('review_success', self::SUCCESS_MESSAGE);
            redirect($productUrl);
        }

        $name = trim((string) input('name'));
        $email = trim((string) input('email'));
        $rating = (int) input('rating');
        $body = trim((string) input('body'));

        if (
            $name === '' || mb_strlen($name) > 100
            || $email === '' || mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || $rating < 1 || $rating > 5
            || $body === ''
        ) {
            setFlash('review_error', self::VALIDATION_ERROR);
            redirect($productUrl);
        }

        reviewCreate(
            (int) $product['id'],
            isAuthenticated() ? (int) $_SESSION['user_id'] : null,
            $name,
            $email,
            $rating,
            $body
        );

        setFlash('review_success', self::SUCCESS_MESSAGE);
        redirect($productUrl);
    }
}
