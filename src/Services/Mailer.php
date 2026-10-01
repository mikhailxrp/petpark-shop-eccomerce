<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Отправка email через PHPMailer/SMTP (FR-AUTH-003, ADR-010: email — канал
 * уведомлений). Настройки — из .env (Mailtrap на local, боевой SMTP на
 * проде). Функция, не класс — единственный тип письма пока, конфиг
 * читается на каждый вызов, отдельный клиент на каждое письмо не нужен
 * (php.md: класс оправдан только когда конфиг/клиент переиспользуется).
 */
function sendPasswordResetEmail(string $toEmail, string $toName, string $newPassword): void
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = env('MAIL_HOST');
        $mail->Port       = (int) env('MAIL_PORT');
        $mail->SMTPAuth   = true;
        $mail->Username   = env('MAIL_USERNAME');
        $mail->Password   = env('MAIL_PASSWORD');
        $mail->SMTPSecure = env('MAIL_ENCRYPTION') === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(env('MAIL_FROM'), env('MAIL_FROM_NAME'));
        $mail->addAddress($toEmail, $toName);

        $mail->Subject = 'Новый пароль — ' . SHOP_NAME;
        $mail->Body    = sprintf(
            "Здравствуйте, %s!\n\nВы запросили восстановление пароля на %s.\nВаш новый пароль: %s\n\nРекомендуем сменить его после входа.",
            $toName,
            SHOP_NAME,
            $newPassword
        );

        $mail->send();
    } catch (PHPMailerException $e) {
        throw new RuntimeException('Не удалось отправить письмо восстановления пароля: ' . $mail->ErrorInfo, 0, $e);
    }
}

/**
 * Письмо с паролем нового аккаунта, автосозданного при первом Заказе
 * (FR-AUTH-002, phase-2.md Таск 5) — отдельное от восстановления
 * пароля письмо: другой повод и текст, хотя оба несут пароль.
 */
function sendNewCustomerAccountEmail(string $toEmail, string $toName, string $password): void
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = env('MAIL_HOST');
        $mail->Port       = (int) env('MAIL_PORT');
        $mail->SMTPAuth   = true;
        $mail->Username   = env('MAIL_USERNAME');
        $mail->Password   = env('MAIL_PASSWORD');
        $mail->SMTPSecure = env('MAIL_ENCRYPTION') === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(env('MAIL_FROM'), env('MAIL_FROM_NAME'));
        $mail->addAddress($toEmail, $toName);

        $mail->Subject = 'Ваш аккаунт создан — ' . SHOP_NAME;
        $mail->Body    = sprintf(
            "Здравствуйте, %s!\n\nВы оформили первый заказ на %s, и для вас автоматически создан личный кабинет.\nEmail для входа: %s\nВаш пароль: %s\n\nРекомендуем сменить его после входа.",
            $toName,
            SHOP_NAME,
            $toEmail,
            $password
        );

        $mail->send();
    } catch (PHPMailerException $e) {
        throw new RuntimeException('Не удалось отправить письмо о новом аккаунте: ' . $mail->ErrorInfo, 0, $e);
    }
}

/**
 * Письмо Владельцу: расход ИИ достиг порога уведомления (phase-5.md, Таск 2;
 * NFR-AI §11.8). Суммы — строки DECIMAL, не float.
 */
function sendAiLimitNotifyEmail(string $toEmail, string $toName, int $percent, string $spent, string $limit): void
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = env('MAIL_HOST');
        $mail->Port       = (int) env('MAIL_PORT');
        $mail->SMTPAuth   = true;
        $mail->Username   = env('MAIL_USERNAME');
        $mail->Password   = env('MAIL_PASSWORD');
        $mail->SMTPSecure = env('MAIL_ENCRYPTION') === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(env('MAIL_FROM'), env('MAIL_FROM_NAME'));
        $mail->addAddress($toEmail, $toName);

        $mail->Subject = 'Расход ИИ-помощников достиг ' . $percent . '% лимита — ' . SHOP_NAME;
        $mail->Body    = sprintf(
            "Здравствуйте, %s!\n\nРасход ИИ-помощников за текущий месяц: %s ₽ из %s ₽ (%d%%).\nПри 100%% приостанавливаются разбор Характеристик и генерация описаний, при 120%% — консультант в чате и разбор Обращений.\n\nПодробности — в админке, раздел «ИИ-помощники».",
            $toName,
            $spent,
            $limit,
            $percent
        );

        $mail->send();
    } catch (PHPMailerException $e) {
        throw new RuntimeException('Не удалось отправить письмо о лимите ИИ: ' . $mail->ErrorInfo, 0, $e);
    }
}
