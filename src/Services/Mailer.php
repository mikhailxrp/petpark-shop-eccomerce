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
