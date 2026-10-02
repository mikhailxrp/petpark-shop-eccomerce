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
