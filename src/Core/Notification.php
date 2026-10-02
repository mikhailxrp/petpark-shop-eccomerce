<?php

declare(strict_types=1);

/**
 * Чистые правила очереди уведомлений (FR-NOTIF-002, ADR-030): лимит попыток и
 * пауза перед повтором. Без БД и HTTP — покрыто tests/Unit/NotificationTest.php.
 * Очередь — Models/Notification.php, отправка — Services/Notifier.php.
 */

const NOTIFICATION_MAX_ATTEMPTS = 5;

// Пауза (минуты) перед попыткой с данным номером; первая уходит сразу.
const NOTIFICATION_DELAY_BEFORE_ATTEMPT = [
    1 => 0,
    2 => 1,
    3 => 5,
    4 => 15,
    5 => 60,
];

// Сколько писем обрабатывает один запрос после ответа — чтобы не тянуть процесс.
const NOTIFICATION_BATCH_SIZE = 5;

// Строка в `sending` дольше этого срока считается брошенной (процесс упал
// посреди отправки) и снова попадает в очередь.
const NOTIFICATION_SENDING_LEASE_MINUTES = 5;

// Письмо на каждый статус Заказа (FR-NOTIF-001 п. 2; `new` — из таблицы
// переходов tz.md §6.3). Хвост темы после «Заказ №N — ».
const NOTIFICATION_ORDER_STATUS_SUBJECTS = [
    'new'              => 'принят',
    'confirmed'        => 'подтверждён',
    'assembled'        => 'собран',
    'shipped'          => 'передан в доставку',
    'ready_for_pickup' => 'готов к выдаче',
    'delivered'        => 'доставлен',
    'picked_up'        => 'получен',
    'cancelled'        => 'отменён',
];

/**
 * Какое письмо уходит при переходе Заказа в $toStatus.
 *
 * @param string $paymentStatusBefore orders.payment_status до перехода
 * @return array{subject: string, refund_note: bool}|null null — для статуса письма нет
 */
function notificationOrderStatusMessage(string $toStatus, string $paymentStatusBefore): ?array
{
    $subject = NOTIFICATION_ORDER_STATUS_SUBJECTS[$toStatus] ?? null;
    if ($subject === null) {
        return null;
    }

    return [
        'subject'     => $subject,
        'refund_note' => $toStatus === 'cancelled' && $paymentStatusBefore === 'paid',
    ];
}

/** Пауза в минутах перед попыткой №$attempt (1..NOTIFICATION_MAX_ATTEMPTS). */
function notificationDelayBeforeAttempt(int $attempt): int
{
    return NOTIFICATION_DELAY_BEFORE_ATTEMPT[$attempt]
        ?? throw new InvalidArgumentException('Номер попытки вне диапазона: ' . $attempt);
}

/**
 * Что делать после неудачной попытки №$attemptsDone.
 *
 * @return int|null минуты до следующей попытки; null — попытки исчерпаны (`failed`)
 */
function notificationRetryDelayAfterFailure(int $attemptsDone): ?int
{
    if ($attemptsDone >= NOTIFICATION_MAX_ATTEMPTS) {
        return null;
    }

    return notificationDelayBeforeAttempt($attemptsDone + 1);
}

// За сколько часов до визита уходит напоминание (FR-NOTIF-001 п. 3).
const NOTIFICATION_BOOKING_REMINDER_HOURS = 3;

// Как часто веб-запросы проверяют, не пора ли напоминать (ADR-030: без cron).
const NOTIFICATION_REMINDER_CHECK_INTERVAL_SECONDS = 60;

/**
 * Нужно ли напоминание о Записи: подтверждена, визит ещё впереди и не дальше
 * NOTIFICATION_BOOKING_REMINDER_HOURS, а сама Запись создана не позже чем за
 * столько же часов до визита (иначе она «свежая» — подтверждение уже и есть
 * напоминание). Тот же критерий в SQL — bookingsDueForReminder().
 */
function notificationBookingReminderDue(
    string $status,
    DateTimeImmutable $scheduledAt,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $now
): bool {
    if ($status !== 'confirmed') {
        return false;
    }

    $lead = new DateInterval('PT' . NOTIFICATION_BOOKING_REMINDER_HOURS . 'H');

    return $scheduledAt > $now
        && $scheduledAt <= $now->add($lead)
        && $createdAt <= $scheduledAt->sub($lead);
}
