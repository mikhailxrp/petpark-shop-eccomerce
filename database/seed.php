<?php

declare(strict_types=1);

/**
 * Сид тестовых пользователей — по одному на каждую роль users.role.
 * Запускать из CLI: php database/seed.php
 * Идемпотентно — INSERT ... ON DUPLICATE KEY UPDATE по email, повторный
 * запуск не плодит дубли и пересинхронизирует пароль при смене в .env.
 *
 * Пароль общий для всех тестовых пользователей — SEED_USER_PASSWORD
 * в .env (только local, не деплоится на прод — см. .env.example).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Доступ только из командной строки: php database/seed.php');
}

require_once dirname(__DIR__) . '/config/config.php';

if (APP_ENV === 'production') {
    fwrite(STDERR, "Сид тестовых пользователей не запускается при APP_ENV=production.\n");
    exit(1);
}

$password = env('SEED_USER_PASSWORD', '');
if (strlen($password) < 8) {
    fwrite(STDERR, "SEED_USER_PASSWORD не задан или короче 8 символов — заполните .env.\n");
    exit(1);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// Роли — полный ENUM users.role (database.md).
$seedUsers = [
    ['name' => 'Виктория Смирнова', 'email' => 'owner@petpark.test',          'phone' => null,             'role' => 'owner'],
    ['name' => 'Администратор смены', 'email' => 'shift-admin@petpark.test',  'phone' => null,             'role' => 'shift_admin'],
    ['name' => 'Контент-редактор',  'email' => 'content-editor@petpark.test', 'phone' => null,             'role' => 'content_editor'],
    ['name' => 'Специалист Груминг', 'email' => 'specialist@petpark.test',    'phone' => null,             'role' => 'specialist'],
    ['name' => 'Тестовый покупатель', 'email' => 'customer@petpark.test',     'phone' => '+79000000000',   'role' => 'customer'],
];

$pdo = getPdo();

$statement = $pdo->prepare('
    INSERT INTO users (name, email, phone, password_hash, role)
    VALUES (:name, :email, :phone, :password_hash, :role)
    ON DUPLICATE KEY UPDATE
        name          = VALUES(name),
        phone         = VALUES(phone),
        password_hash = VALUES(password_hash),
        role          = VALUES(role)
');

foreach ($seedUsers as $user) {
    $statement->execute([
        'name'          => $user['name'],
        'email'         => $user['email'],
        'phone'         => $user['phone'],
        'password_hash' => $passwordHash,
        'role'          => $user['role'],
    ]);
}

echo "✅ Тестовые пользователи созданы/обновлены (" . count($seedUsers) . ").\n";
