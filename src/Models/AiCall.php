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
