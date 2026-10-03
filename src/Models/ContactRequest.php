<?php

declare(strict_types=1);

/**
 * Модель обращений с формы `/contacts` — только SQL через PDO (php.md).
 * `database.md` (`contact_requests`).
 */

/**
 * Сохранить обращение.
 *
 * @return int id новой строки
 */
function contactRequestCreate(string $name, string $phone, string $email, string $message): int
{
    $pdo = getPdo();
    $stmt = $pdo->prepare(
        'INSERT INTO contact_requests (name, phone, email, message) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$name, $phone, $email, $message]);

    return (int) $pdo->lastInsertId();
}
