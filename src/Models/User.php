<?php

declare(strict_types=1);

/**
 * Модель Пользователя — только SQL через PDO, возвращает массивы (php.md).
 * Регистрации в проекте нет (Q-027) — только чтение существующих
 * пользователей (сид ролей, `database/seed.php`), смена пароля при
 * восстановлении (FR-AUTH-003) и правка личных данных Покупателя
 * (FR-ACC-005), а также учётные записи персонала (FR-ADM-003) — создание,
 * смена роли, отключение (`users.is_active`, ADR-033); у Специалиста
 * создание заводит и профиль (график, Услуги — phase-7.md, Таск 6).
 */

/**
 * @return array<string, mixed>|null
 */
function userFindByEmail(string $email): ?array
{
    $stmt = getPdo()->prepare('
        SELECT id, name, email, phone, password_hash, role, is_active
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

/**
 * Активна ли учётная запись. Несуществующая строка — не активна: удалённого
 * пользователя открытая сессия не должна пропускать (requireRole()).
 */
function userIsActive(int $userId): bool
{
    $stmt = getPdo()->prepare('SELECT is_active FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);

    return (int) $stmt->fetchColumn() === 1;
}

/**
 * Весь персонал (всё, кроме Покупателей), для /admin/staff.
 *
 * @return list<array<string, mixed>>
 */
function userListStaff(): array
{
    $stmt = getPdo()->query('
        SELECT id, name, email, phone, role, is_active, created_at
        FROM users
        WHERE role <> \'customer\'
        ORDER BY is_active DESC, name ASC, id ASC
    ');

    return $stmt->fetchAll();
}

/**
 * @return array<string, mixed>|null null — нет такого id или это Покупатель
 */
function userFindStaffById(int $id): ?array
{
    $stmt = getPdo()->prepare('
        SELECT id, name, email, phone, role, is_active
        FROM users
        WHERE id = :id AND role <> \'customer\'
        LIMIT 1
    ');
    $stmt->execute(['id' => $id]);

    $user = $stmt->fetch();
    return $user !== false ? $user : null;
}

/**
 * Создание сотрудника (FR-ADM-003). Занятый email ловится уникальным
 * индексом, а не предварительным SELECT — так нет гонки. Для Специалиста
 * строка `users`, `specialists` и связи с Услугами пишутся одной транзакцией.
 *
 * @param array{work_start: string, work_end: string, day_off: ?int, service_ids: list<int>}|null $specialist
 *        профиль Специалиста; null — другие роли
 * @return int|null id новой записи; null — email уже занят
 */
function userCreateStaff(
    string $name,
    string $email,
    ?string $phone,
    string $role,
    string $passwordHash,
    ?array $specialist = null
): ?int {
    $pdo = getPdo();
    $stmt = $pdo->prepare('
        INSERT INTO users (name, email, phone, password_hash, role)
        VALUES (:name, :email, :phone, :password_hash, :role)
    ');

    $pdo->beginTransaction();

    try {
        $stmt->execute([
            'name'          => $name,
            'email'         => $email,
            'phone'         => $phone,
            'password_hash' => $passwordHash,
            'role'          => $role,
        ]);
        $userId = (int) $pdo->lastInsertId();

        if ($specialist !== null) {
            specialistCreate(
                $userId,
                $specialist['work_start'],
                $specialist['work_end'],
                $specialist['day_off'],
                $specialist['service_ids']
            );
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e instanceof PDOException && $e->getCode() === '23000') {
            return null;
        }
        throw $e;
    }

    return $userId;
}

/**
 * Откат только что созданного сотрудника, если письмо с паролем не ушло:
 * без письма пароль не знает никто. Покупателей не трогает.
 */
function userDeleteStaffById(int $id): void
{
    $stmt = getPdo()->prepare('DELETE FROM users WHERE id = :id AND role <> \'customer\'');
    $stmt->execute(['id' => $id]);
}

function userUpdateRole(int $userId, string $role): void
{
    $stmt = getPdo()->prepare('UPDATE users SET role = :role WHERE id = :id AND role <> \'customer\'');
    $stmt->execute(['role' => $role, 'id' => $userId]);
}

function userSetActive(int $userId, bool $isActive): void
{
    $stmt = getPdo()->prepare('UPDATE users SET is_active = :is_active WHERE id = :id AND role <> \'customer\'');
    $stmt->execute(['is_active' => $isActive ? 1 : 0, 'id' => $userId]);
}
