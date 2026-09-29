<?php

declare(strict_types=1);

/**
 * Модель Пользователя — только SQL через PDO, возвращает массивы (php.md).
 * Регистрации в проекте нет (Q-027) — только чтение существующих
 * пользователей (сид ролей, `database/seed.php`) и смена пароля при
 * восстановлении (FR-AUTH-003).
 */

/**
 * @return array<string, mixed>|null
 */
function userFindByEmail(string $email): ?array
{
    $stmt = getPdo()->prepare('
        SELECT id, name, email, phone, password_hash, role
        FROM users
        WHERE email = :email
        LIMIT 1
    ');
    $stmt->execute(['email' => $email]);

    $user = $stmt->fetch();
    return $user !== false ? $user : null;
}

/**
 * Контакты авторизованного Покупателя для автоподстановки на оформлении
 * (FR-CHK-001, правило 2) — без password_hash, он здесь не нужен.
 *
 * @return array<string, mixed>|null
 */
function userFindById(int $id): ?array
{
    $stmt = getPdo()->prepare('
        SELECT id, name, email, phone, role
        FROM users
        WHERE id = :id
        LIMIT 1
    ');
    $stmt->execute(['id' => $id]);

    $user = $stmt->fetch();
    return $user !== false ? $user : null;
}

function userUpdatePassword(int $userId, string $passwordHash): void
{
    $stmt = getPdo()->prepare('
        UPDATE users
        SET password_hash = :password_hash
        WHERE id = :id
    ');
    $stmt->execute(['password_hash' => $passwordHash, 'id' => $userId]);
}
