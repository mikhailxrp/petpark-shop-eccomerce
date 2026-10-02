<?php

declare(strict_types=1);

/**
 * Отправка очереди уведомлений (FR-NOTIF-002, ADR-030). Cron нет: письма
 * уходят после ответа на запрос, повторы — тем же путём на следующих запросах.
 * Исключения наружу не выходят — статус Заказа/Записи от почты не зависит.
 */

// Случайный хвост ключа события «сообщение по Возврату» (hex = вдвое длиннее).
const NOTIFICATION_MESSAGE_KEY_BYTES = 8;

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
 * Поставить в очередь письмо о новом статусе Заказа. Вызывается внутри
 * транзакции смены статуса (Models/Order.php): текст фиксируется в строке
 * очереди сразу, откат статуса откатывает и письмо. Без email — пропуск.
 *
 * @param array{id: int|string, contact_email: ?string, contact_name: string, total: string, delivery_method: string} $order
 */
function notifierEnqueueOrderStatus(array $order, string $toStatus, string $paymentStatusBefore): void
{
    $message = notificationOrderStatusMessage($toStatus, $paymentStatusBefore);
    if ($message === null) {
        return;
    }

    $orderId = (int) $order['id'];
    $email = trim((string) ($order['contact_email'] ?? ''));
    if ($email === '') {
        logWarning('Уведомление о Заказе не поставлено: нет email', ['order_id' => $orderId, 'status' => $toStatus]);
        return;
    }

    $body = renderToString('emails/order-status', [
        'order'       => $order,
        'status'      => $toStatus,
        'refund_note' => $message['refund_note'],
    ]);

    notificationEnqueue(
        'order:' . $orderId . ':status:' . $toStatus,
        $email,
        (string) $order['contact_name'],
        'Заказ №' . $orderId . ' — ' . $message['subject'] . ' — ' . SHOP_NAME,
        $body
    );
}

/**
 * Поставить в очередь письмо о подтверждении Записи. Вызывается внутри
 * транзакции подтверждения (Models/Booking.php): откат откатывает и письмо.
 * Без email — пропуск с предупреждением, подтверждение не блокируется.
 */
function notifierEnqueueBookingConfirmed(int $bookingId): void
{
    $booking = bookingNotificationData($bookingId);
    if ($booking === null) {
        return;
    }

    $email = trim((string) $booking['customer_email']);
    if ($email === '') {
        logWarning('Уведомление о Записи не поставлено: нет email', ['booking_id' => $bookingId, 'kind' => 'confirmed']);
        return;
    }

    notificationEnqueue(
        'booking:' . $bookingId . ':confirmed',
        $email,
        (string) $booking['customer_name'],
        'Запись подтверждена — ' . SHOP_NAME,
        renderToString('emails/booking-confirmed', ['booking' => $booking])
    );
}

/**
 * Поставить в очередь письмо о статусе Возврата. Вызывается внутри транзакции
 * Models/OrderReturn.php: откат откатывает и письмо. Без email — пропуск с
 * предупреждением, смена статуса не блокируется.
 *
 * @param array{id: int|string, order_id: int|string, contact_name: ?string, contact_email: ?string, total: string, decision_comment: ?string} $return
 */
function notifierEnqueueReturnStatus(array $return, string $toStatus): void
{
    $subject = RETURN_STATUS_SUBJECTS[$toStatus] ?? null;
    if ($subject === null) {
        return;
    }

    $returnId = (int) $return['id'];
    $email = trim((string) ($return['contact_email'] ?? ''));
    if ($email === '') {
        logWarning('Уведомление о Возврате не поставлено: нет email', ['return_id' => $returnId, 'status' => $toStatus]);
        return;
    }

    notificationEnqueue(
        'return:' . $returnId . ':status:' . $toStatus,
        $email,
        (string) ($return['contact_name'] ?? ''),
        'Возврат по заказу №' . (int) $return['order_id'] . ' — ' . $subject . ' — ' . SHOP_NAME,
        renderToString('emails/return-status', ['return' => $return, 'status' => $toStatus])
    );
}

/**
 * Поставить в очередь сообщение Покупателю по заявке на Возврат («Написать
 * покупателю», FR-RET-002 п. 3). Статус заявки не меняется. Ключ события
 * уникален на каждое сообщение — второе письмо не считается дублем первого.
 *
 * @param array{id: int|string, order_id: int|string, contact_name: ?string, contact_email: ?string} $return
 * @return bool false — у заявки нет email, письмо не поставлено
 */
function notifierEnqueueReturnMessage(array $return, string $message): bool
{
    $returnId = (int) $return['id'];
    $email = trim((string) ($return['contact_email'] ?? ''));
    if ($email === '') {
        logWarning('Сообщение по Возврату не поставлено: нет email', ['return_id' => $returnId]);
        return false;
    }

    return notificationEnqueue(
        'return:' . $returnId . ':message:' . bin2hex(random_bytes(NOTIFICATION_MESSAGE_KEY_BYTES)),
        $email,
        (string) ($return['contact_name'] ?? ''),
        'Возврат по заказу №' . (int) $return['order_id'] . ' — сообщение от магазина — ' . SHOP_NAME,
        renderToString('emails/return-message', ['return' => $return, 'message' => $message])
    );
}

/**
 * Напоминания за NOTIFICATION_BOOKING_REMINDER_HOURS до визита. Проверка идёт
 * на веб-запросах не чаще раза в NOTIFICATION_REMINDER_CHECK_INTERVAL_SECONDS
 * (отметка времени — файл в storage/cache/); дубль исключён ключом
 * `booking:{id}:reminder`. Исключения наружу не выходят.
 */
function notifierEnqueueBookingReminders(): void
{
    try {
        if (cacheGet('notifier', 'reminders-last-run', NOTIFICATION_REMINDER_CHECK_INTERVAL_SECONDS) !== null) {
            return;
        }
        cachePut('notifier', 'reminders-last-run', (string) time());

        foreach (bookingsDueForReminder() as $bookingId) {
            $booking = bookingNotificationData($bookingId);
            $email = trim((string) ($booking['customer_email'] ?? ''));
            if ($booking === null || $email === '') {
                logWarning('Напоминание о Записи не поставлено: нет email', ['booking_id' => $bookingId]);
                continue;
            }

            notificationEnqueue(
                'booking:' . $bookingId . ':reminder',
                $email,
                (string) $booking['customer_name'],
                'Напоминание о записи — ' . SHOP_NAME,
                renderToString('emails/booking-reminder', ['booking' => $booking])
            );
        }
    } catch (Throwable $e) {
        logException($e);
    }
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
        notifierEnqueueBookingReminders();
        notifierSendPending();
    });
}
