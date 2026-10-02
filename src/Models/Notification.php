<?php

declare(strict_types=1);

/**
 * Модель очереди уведомлений (outbox) — только SQL через PDO (php.md).
 * `database.md` (`notifications`). Время считает БД (`NOW()`), не PHP.
 *
 * notificationEnqueue() не открывает свою транзакцию: её вызывают внутри
 * транзакции смены статуса, и откат статуса откатывает письмо (FR-NOTIF-002).
 */

/**
 * Поставить письмо в очередь. Тот же $eventKey второй раз — ничего не делает.
 *
 * @return bool true — строка создана, false — такое событие уже было
 */
function notificationEnqueue(
    string $eventKey,
    string $toEmail,
    string $toName,
    string $subject,
    string $body
): bool {
    $stmt = getPdo()->prepare(
        'INSERT IGNORE INTO notifications (event_key, recipient_email, recipient_name, subject, body)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$eventKey, $toEmail, $toName, $subject, $body]);

    return $stmt->rowCount() === 1;
}

/**
 * id писем, которым пора: ожидающие с наступившим временем и «брошенные»
 * в `sending` (срок аренды истёк). Исчерпавшие попытки не берутся.
 *
 * @return int[]
 */
function notificationFindDueIds(int $limit): array
{
    $stmt = getPdo()->prepare(
        "SELECT id FROM notifications
         WHERE status IN ('pending', 'sending') AND next_attempt_at <= NOW() AND attempts < :max
         ORDER BY next_attempt_at, id
         LIMIT :limit"
    );
    $stmt->bindValue(':max', NOTIFICATION_MAX_ATTEMPTS, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Захватить письмо: условный UPDATE, выигрывает ровно один параллельный запрос.
 * Попытка засчитывается сразу — упавший посреди отправки процесс не даёт
 * повторять бесконечно. next_attempt_at на время отправки служит арендой.
 *
 * @return array<string, mixed>|null письмо, если захват удался
 */
function notificationClaim(int $id): ?array
{
    $pdo  = getPdo();
    $stmt = $pdo->prepare(
        "UPDATE notifications
         SET status = 'sending', attempts = attempts + 1,
             next_attempt_at = DATE_ADD(NOW(), INTERVAL :lease MINUTE)
         WHERE id = :id AND status IN ('pending', 'sending')
           AND next_attempt_at <= NOW() AND attempts < :max"
    );
    $stmt->bindValue(':lease', NOTIFICATION_SENDING_LEASE_MINUTES, PDO::PARAM_INT);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->bindValue(':max', NOTIFICATION_MAX_ATTEMPTS, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() !== 1) {
        return null;
    }

    $row = $pdo->prepare(
        'SELECT id, recipient_email, recipient_name, subject, body, attempts
         FROM notifications WHERE id = ?'
    );
    $row->execute([$id]);

    return $row->fetch() ?: null;
}

function notificationMarkSent(int $id): void
{
    getPdo()->prepare(
        "UPDATE notifications SET status = 'sent', sent_at = NOW(), last_error = NULL WHERE id = ?"
    )->execute([$id]);
}

/**
 * Записать неудачную попытку: ещё есть попытки — снова `pending` с паузой,
 * иначе `failed`.
 *
 * @param int|null $retryInMinutes null — попытки исчерпаны
 */
function notificationMarkAttemptFailed(int $id, string $error, ?int $retryInMinutes): void
{
    $error = mb_substr($error, 0, 500);

    if ($retryInMinutes === null) {
        getPdo()->prepare(
            "UPDATE notifications SET status = 'failed', last_error = ? WHERE id = ?"
        )->execute([$error, $id]);

        return;
    }

    getPdo()->prepare(
        "UPDATE notifications
         SET status = 'pending', last_error = ?, next_attempt_at = DATE_ADD(NOW(), INTERVAL ? MINUTE)
         WHERE id = ?"
    )->execute([$error, $retryInMinutes, $id]);
}

/**
 * «Брошенные» в `sending`, у которых попытки уже исчерпаны, — в `failed`:
 * иначе они остались бы в очереди навсегда.
 *
 * @return int сколько строк закрыто
 */
function notificationFailAbandoned(): int
{
    $stmt = getPdo()->prepare(
        "UPDATE notifications
         SET status = 'failed', last_error = 'Отправка прервана'
         WHERE status = 'sending' AND next_attempt_at <= NOW() AND attempts >= ?"
    );
    $stmt->execute([NOTIFICATION_MAX_ATTEMPTS]);

    return $stmt->rowCount();
}
