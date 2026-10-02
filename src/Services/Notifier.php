<?php

declare(strict_types=1);

/**
 * Отправка очереди уведомлений (FR-NOTIF-002, ADR-030). Cron нет: письма
 * уходят после ответа на запрос, повторы — тем же путём на следующих запросах.
 * Исключения наружу не выходят — статус Заказа/Записи от почты не зависит.
 */

/**
 * Отправить письма, которым пора, не больше $limit за вызов.
 *
 * @return array{sent: int, retry: int, failed: int}
 */
function notifierSendPending(int $limit = NOTIFICATION_BATCH_SIZE): array
{
    $result = ['sent' => 0, 'retry' => 0, 'failed' => 0];

    try {
        $abandoned = notificationFailAbandoned();
        if ($abandoned > 0) {
            $result['failed'] += $abandoned;
            logWarning('Уведомления: отправка прервана, попытки исчерпаны', ['count' => $abandoned]);
        }

        foreach (notificationFindDueIds($limit) as $id) {
            $row = notificationClaim($id);
            if ($row === null) {
                continue; // письмо забрал параллельный запрос
            }

            try {
                sendEmail($row['recipient_email'], $row['recipient_name'], $row['subject'], $row['body']);
                notificationMarkSent($id);
                $result['sent']++;
            } catch (Throwable $e) {
                $retryIn = notificationRetryDelayAfterFailure((int) $row['attempts']);
                notificationMarkAttemptFailed($id, $e->getMessage(), $retryIn);

                if ($retryIn === null) {
                    $result['failed']++;
                    logWarning('Уведомление не отправлено, попытки исчерпаны', [
                        'notification_id' => $id,
                        'attempts'        => (int) $row['attempts'],
                    ]);
                } else {
                    $result['retry']++;
                }
            }
        }
    } catch (Throwable $e) {
        logException($e);
    }

    return $result;
}

/**
 * Запланировать отправку очереди на конец текущего запроса. Безопасно звать
 * несколько раз — зарегистрируется один обработчик.
 */
function notifierSendAfterResponse(): void
{
    static $registered = false;
    if ($registered) {
        return;
    }
    $registered = true;

    register_shutdown_function(static function (): void {
        // На PHP-FPM/FastCGI ответ уходит клиенту до отправки писем. На Apache
        // с mod_php такой функции нет — запрос, в котором есть что отправлять,
        // дождётся SMTP (ограничено MAIL_SMTP_TIMEOUT_SECONDS).
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        // Снять блокировку сессии: иначе другой запрос того же пользователя
        // ждал бы окончания SMTP-соединения.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        notifierSendPending();
    });
}
