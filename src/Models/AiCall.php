<?php

declare(strict_types=1);

/**
 * Модель журнала вызовов ИИ — только SQL через PDO (php.md).
 * `database.md` (`ai_calls`). Промпт хранится здесь и больше нигде —
 * в app.log он не пишется (возможные ПДн).
 */

function aiCallInsert(
    string $task,
    string $taskClass,
    string $provider,
    string $prompt,
    int $tokens,
    string $cost,
    string $status
): int {
    $pdo = getPdo();
    $pdo->prepare(
        'INSERT INTO ai_calls (task, task_class, provider, prompt, tokens, cost, status)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([$task, $taskClass, $provider, $prompt, $tokens, $cost, $status]);

    return (int) $pdo->lastInsertId();
}

/** Расход с начала текущего календарного месяца, строкой DECIMAL. */
function aiCallsMonthSpent(): string
{
    $stmt = getPdo()->query(
        "SELECT COALESCE(SUM(cost), 0) FROM ai_calls
         WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
    );

    return (string) $stmt->fetchColumn();
}

/**
 * Ленивая очистка журнала по срокам хранения (AI_RETENTION_MONTHS).
 *
 * @return int сколько записей удалено
 */
function aiCallsPurgeExpired(): int
{
    $deleted = 0;
    $stmt = getPdo()->prepare(
        'DELETE FROM ai_calls WHERE task_class = ? AND created_at < DATE_SUB(NOW(), INTERVAL ? MONTH)'
    );
    foreach (AI_RETENTION_MONTHS as $class => $months) {
        $stmt->execute([$class, $months]);
        $deleted += $stmt->rowCount();
    }

    return $deleted;
}

/**
 * Страница журнала — без текста промпта (возможные ПДн, на странице не нужен).
 *
 * @return array<int, array<string, mixed>>
 */
function aiCallsList(int $limit, int $offset): array
{
    $stmt = getPdo()->prepare(
        'SELECT id, task, task_class, provider, tokens, cost, status, created_at
         FROM ai_calls
         ORDER BY id DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function aiCallsCount(): int
{
    return (int) getPdo()->query('SELECT COUNT(*) FROM ai_calls')->fetchColumn();
}

/**
 * Исходы черновиков (`ai_draft_outcomes`) по видам: accepted / edited / rejected.
 *
 * @return array<string, int>
 */
function aiDraftOutcomeCounts(): array
{
    $counts = ['accepted' => 0, 'edited' => 0, 'rejected' => 0];
    $stmt = getPdo()->query('SELECT outcome, COUNT(*) AS total FROM ai_draft_outcomes GROUP BY outcome');
    foreach ($stmt->fetchAll() as $row) {
        $counts[(string) $row['outcome']] = (int) $row['total'];
    }

    return $counts;
}

/**
 * Занять отметку «уведомление отправлено» за текущий месяц (`ai_notifications`).
 * true — отметка поставлена только что, письмо отправляет этот запрос;
 * false — уже была (UNIQUE + INSERT IGNORE: атомарно при параллельных заходах).
 * Месяц считает БД — тем же `NOW()`, что и aiCallsMonthSpent().
 */
function aiNotificationClaim(string $kind): bool
{
    $stmt = getPdo()->prepare(
        "INSERT IGNORE INTO ai_notifications (kind, period) VALUES (?, DATE_FORMAT(NOW(), '%Y-%m'))"
    );
    $stmt->execute([$kind]);

    return $stmt->rowCount() === 1;
}

/** Снять отметку (письмо не ушло) — следующий заход повторит отправку. */
function aiNotificationRelease(string $kind): void
{
    getPdo()->prepare(
        "DELETE FROM ai_notifications WHERE kind = ? AND period = DATE_FORMAT(NOW(), '%Y-%m')"
    )->execute([$kind]);
}

/**
 * Контакт Владельца для письма о лимите.
 *
 * @return array{name: string, email: string}|null
 */
function aiOwnerContact(): ?array
{
    $stmt = getPdo()->query("SELECT name, email FROM users WHERE role = 'owner' ORDER BY id LIMIT 1");
    $owner = $stmt->fetch();

    return $owner !== false ? ['name' => (string) $owner['name'], 'email' => (string) $owner['email']] : null;
}
