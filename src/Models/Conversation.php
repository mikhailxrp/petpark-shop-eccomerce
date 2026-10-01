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

/**
 * Плейсхолдеры `IN (:ch0, :ch1, …)` для списка включённых Каналов
 * (FR-CHANNELS-005). Пустой список (все Каналы выключены) — вызывающий код
 * в БД не ходит.
 *
 * @param array<int, string> $channels
 * @return array{0: string, 1: array<string, string>}
 */
function conversationChannelFilter(array $channels): array
{
    $params = [];
    foreach (array_values($channels) as $index => $channel) {
        $params['ch' . $index] = $channel;
    }

    return [implode(', ', array_map(static fn (string $name): string => ':' . $name, array_keys($params))), $params];
}

/** @param array<int, string> $channels */
function conversationCount(array $channels): int
{
    if ($channels === []) {
        return 0;
    }

    [$in, $params] = conversationChannelFilter($channels);
    $stmt = getPdo()->prepare("SELECT COUNT(*) FROM conversations WHERE channel IN ({$in})");
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

/**
 * Обращения с последним сообщением, новые сверху (по времени последнего
 * сообщения). Имя Покупателя — из users, если опознан. `$extraWhere` — только
 * константные куски SQL из этого файла, значения — через `$params`.
 *
 * @param array<int, string> $channels
 * @param array<string, int> $params
 * @return array<int, array<string, mixed>>
 */
function conversationListQuery(array $channels, string $extraWhere, array $params, int $limit, int $offset): array
{
    if ($channels === []) {
        return [];
    }

    [$in, $channelParams] = conversationChannelFilter($channels);
    $stmt = getPdo()->prepare("
        SELECT
            c.id, c.channel, c.contact_identifier, c.sender_name, c.is_read,
            u.name AS customer_name,
            m.id AS last_message_id, m.body AS last_body, m.sent_at AS last_sent_at
        FROM conversations c
        LEFT JOIN users u ON u.id = c.user_id
        JOIN conversation_messages m ON m.id = (
            SELECT m2.id FROM conversation_messages m2
            WHERE m2.conversation_id = c.id
            ORDER BY m2.sent_at DESC, m2.id DESC
            LIMIT 1
        )
        WHERE c.channel IN ({$in}) {$extraWhere}
        ORDER BY m.sent_at DESC, c.id DESC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($channelParams as $name => $value) {
        $stmt->bindValue($name, $value);
    }
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value, PDO::PARAM_INT);
    }
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * @param array<int, string> $channels
 * @return array<int, array<string, mixed>>
 */
function conversationList(array $channels, int $limit, int $offset): array
{
    return conversationListQuery($channels, '', [], $limit, $offset);
}

/**
 * Обращения, в которых есть сообщения новее `$sinceMessageId` (опрос панели).
 *
 * @param array<int, string> $channels
 * @return array<int, array<string, mixed>>
 */
function conversationChangedSince(array $channels, int $sinceMessageId, int $limit): array
{
    return conversationListQuery(
        $channels,
        'AND EXISTS (SELECT 1 FROM conversation_messages n WHERE n.conversation_id = c.id AND n.id > :since)',
        ['since' => $sinceMessageId],
        $limit,
        0
    );
}

/**
 * Наибольший id сообщения среди включённых Каналов — отправная точка опроса.
 *
 * @param array<int, string> $channels
 */
function conversationMaxMessageId(array $channels): int
{
    if ($channels === []) {
        return 0;
    }

    [$in, $params] = conversationChannelFilter($channels);
    $stmt = getPdo()->prepare("
        SELECT COALESCE(MAX(m.id), 0)
        FROM conversation_messages m
        JOIN conversations c ON c.id = m.conversation_id
        WHERE c.channel IN ({$in})
    ");
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
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

/** @return array<int, array<string, mixed>> */
function conversationMessagesSince(int $conversationId, int $sinceMessageId): array
{
    $stmt = getPdo()->prepare('
        SELECT id, direction, body, sent_at
        FROM conversation_messages
        WHERE conversation_id = :id AND id > :since
        ORDER BY sent_at, id
    ');
    $stmt->execute(['id' => $conversationId, 'since' => $sinceMessageId]);

    return $stmt->fetchAll();
}

/** Исходящее сообщение (FR-CHANNELS-002); возвращает id сообщения. */
function conversationAddOutgoing(int $conversationId, string $body): int
{
    $stmt = getPdo()->prepare("
        INSERT INTO conversation_messages (conversation_id, direction, body)
        VALUES (:id, 'out', :body)
    ");
    $stmt->execute(['id' => $conversationId, 'body' => $body]);

    return (int) getPdo()->lastInsertId();
}

/** external_conversation_id последнего Обращения Канала — для «Сымитировать входящее». */
function conversationLatestExternalId(string $channel): ?string
{
    $stmt = getPdo()->prepare('
        SELECT external_conversation_id FROM conversations
        WHERE channel = :channel AND external_conversation_id IS NOT NULL
        ORDER BY updated_at DESC, id DESC
        LIMIT 1
    ');
    $stmt->execute(['channel' => $channel]);
    $value = $stmt->fetchColumn();

    return $value === false ? null : (string) $value;
}

/**
 * Единый код приёма входящего сообщения: его вызывают и «Сымитировать
 * входящее», и будущий вебхук Канала (ADR-001). Обращение ищется по
 * (channel, external_conversation_id) или создаётся — с опознанием
 * Покупателя по телефону; сообщение `in` делает его непрочитанным.
 * Возвращает id Обращения.
 */
function conversationReceive(string $channel, string $externalId, ?string $senderName, ?string $contact, string $body): int
{
    $pdo = getPdo();
    $userId = $contact !== null ? conversationFindUserIdByPhone($contact) : null;

    $pdo->beginTransaction();
    try {
        // У существующего Обращения Покупатель и контакт уже определены — меняем только флаг.
        $stmt = $pdo->prepare('
            INSERT INTO conversations (channel, external_conversation_id, user_id, contact_identifier, sender_name, is_read)
            VALUES (:channel, :external, :user_id, :contact, :sender, 0)
            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), is_read = 0
        ');
        $stmt->execute([
            'channel'  => $channel,
            'external' => $externalId,
            'user_id'  => $userId,
            'contact'  => $contact,
            'sender'   => $senderName,
        ]);
        $conversationId = (int) $pdo->lastInsertId();

        $message = $pdo->prepare("
            INSERT INTO conversation_messages (conversation_id, direction, body)
            VALUES (:id, 'in', :body)
        ");
        $message->execute(['id' => $conversationId, 'body' => $body]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return $conversationId;
}
