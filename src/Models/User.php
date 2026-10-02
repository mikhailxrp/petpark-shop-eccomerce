<?php

declare(strict_types=1);

/**
 * Модель Пользователя — только SQL через PDO, возвращает массивы (php.md).
 * Регистрации в проекте нет (Q-027) — только чтение существующих
 * пользователей (сид ролей, `database/seed.php`), смена пароля при
 * восстановлении (FR-AUTH-003) и правка личных данных Покупателя
 * (FR-ACC-005).
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

/**
 * Автосоздание аккаунта Покупателя при первом Заказе (FR-AUTH-002,
 * phase-2.md Таск 5) — вызывается внутри транзакции orderCreate(),
 * тем же PDO-подключением, чтобы откат Заказа откатил и аккаунт.
 */
function userCreateCustomer(string $name, string $email, string $phone, string $passwordHash): int
{
    $stmt = getPdo()->prepare('
        INSERT INTO users (name, email, phone, password_hash, role)
        VALUES (:name, :email, :phone, :password_hash, \'customer\')
    ');
    $stmt->execute([
        'name'          => $name,
        'email'         => $email,
        'phone'         => $phone,
        'password_hash' => $passwordHash,
    ]);

    return (int) getPdo()->lastInsertId();
}

/**
 * Личные данные Покупателя (FR-ACC-005). Занятый чужой email ловится
 * уникальным индексом `users.email`, а не предварительным SELECT — так нет
 * гонки между проверкой и записью.
 *
 * @return bool false — email уже принадлежит другому аккаунту
 */
function userUpdateProfile(int $userId, string $name, string $email, string $phone): bool
{
    $stmt = getPdo()->prepare('
        UPDATE users
        SET name = :name, email = :email, phone = :phone
        WHERE id = :id
    ');

    try {
        $stmt->execute(['name' => $name, 'email' => $email, 'phone' => $phone, 'id' => $userId]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return false;
        }
        throw $e;
    }

    return true;
}
