<?php

declare(strict_types=1);

/**
 * Модель Обращений единого инбокса — только SQL через PDO, возвращает
 * массивы (phase-5.md, Таск 7; FR-CHANNELS-001). Каналы — заглушка (ADR-001):
 * строки появляются из сида, позже — из того же кода приёма (Таск 8).
 */

/**
 * Покупатель по телефону Обращения. Телефоны в `users.phone` хранятся как
 * ввёл пользователь, поэтому сравниваем только цифры; кандидаты — `7…` и
 * `8…` (оба варианта записи одного номера). Таблица users небольшая,
 * индекс под этот поиск не заводим.
 */
function conversationFindUserIdByPhone(string $phone): ?int
{
    $normalized = normalizePhone($phone);
    if ($normalized === null) {
        return null;
    }

    $ten = substr($normalized, 2);

    $stmt = getPdo()->prepare("
        SELECT id FROM users
        WHERE role = 'customer'
          AND phone IS NOT NULL
          AND REGEXP_REPLACE(phone, '[^0-9]', '') IN (:with_seven, :with_eight)
        ORDER BY id
        LIMIT 1
    ");
    $stmt->execute(['with_seven' => '7' . $ten, 'with_eight' => '8' . $ten]);
    $id = $stmt->fetchColumn();

    return $id === false ? null : (int) $id;
}

function conversationCount(): int
{
    return (int) getPdo()->query('SELECT COUNT(*) FROM conversations')->fetchColumn();
}

/**
 * Страница списка: Обращения с последним сообщением, новые сверху (по
 * времени последнего сообщения). Имя Покупателя — из users, если опознан.
 *
 * @return array<int, array<string, mixed>>
 */
function conversationList(int $limit, int $offset): array
{
    $stmt = getPdo()->prepare('
        SELECT
            c.id, c.channel, c.contact_identifier, c.sender_name, c.is_read,
            u.name AS customer_name,
            m.body AS last_body, m.sent_at AS last_sent_at
        FROM conversations c
        LEFT JOIN users u ON u.id = c.user_id
        JOIN conversation_messages m ON m.id = (
            SELECT m2.id FROM conversation_messages m2
            WHERE m2.conversation_id = c.id
            ORDER BY m2.sent_at DESC, m2.id DESC
            LIMIT 1
        )
        ORDER BY m.sent_at DESC, c.id DESC
        LIMIT :limit OFFSET :offset
    ');
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/** @return array<string, mixed>|null */
function conversationFind(int $id): ?array
{
    $stmt = getPdo()->prepare('
        SELECT
            c.id, c.channel, c.contact_identifier, c.sender_name, c.is_read, c.user_id,
            u.name AS customer_name
        FROM conversations c
        LEFT JOIN users u ON u.id = c.user_id
        WHERE c.id = :id
    ');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/** @return array<int, array<string, mixed>> */
function conversationMessages(int $conversationId): array
{
    $stmt = getPdo()->prepare('
        SELECT id, direction, body, sent_at
        FROM conversation_messages
        WHERE conversation_id = :id
        ORDER BY sent_at, id
    ');
    $stmt->execute(['id' => $conversationId]);

    return $stmt->fetchAll();
}

function conversationMarkRead(int $id): void
{
    $stmt = getPdo()->prepare('UPDATE conversations SET is_read = 1 WHERE id = :id AND is_read = 0');
    $stmt->execute(['id' => $id]);
}
