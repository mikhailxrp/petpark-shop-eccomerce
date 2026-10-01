<?php

declare(strict_types=1);

/**
 * Модель Питомцев — только SQL через PDO, возвращает массивы (php.md).
 * `FR-ACC-003`, `database.md` (`pets`). Любая выборка/правка идёт с
 * `user_id` — чужого Питомца по id не получить (dod-global.md).
 */

/**
 * @return array<int, array<string, mixed>>
 */
function petsByUser(int $userId): array
{
    $stmt = getPdo()->prepare(
        'SELECT id, name, species, breed, weight FROM pets WHERE user_id = ? ORDER BY name, id'
    );
    $stmt->execute([$userId]);

    return $stmt->fetchAll();
}

/**
 * @return array<string, mixed>|null null — Питомца нет или он чужой
 */
function petFind(int $userId, int $petId): ?array
{
    $stmt = getPdo()->prepare(
        'SELECT id, name, species, breed, weight FROM pets WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$petId, $userId]);
    $pet = $stmt->fetch();

    return $pet === false ? null : $pet;
}

function petCreate(int $userId, string $name, string $species, ?string $breed, ?string $weight): int
{
    $pdo = getPdo();
    $pdo->prepare('INSERT INTO pets (user_id, name, species, breed, weight) VALUES (?, ?, ?, ?, ?)')
        ->execute([$userId, $name, $species, $breed, $weight]);

    return (int) $pdo->lastInsertId();
}

function petUpdate(int $userId, int $petId, string $name, string $species, ?string $breed, ?string $weight): void
{
    getPdo()->prepare('UPDATE pets SET name = ?, species = ?, breed = ?, weight = ? WHERE id = ? AND user_id = ?')
        ->execute([$name, $species, $breed, $weight, $petId, $userId]);
}

/**
 * Удаляет Питомца, только если у него нет Записей — одним запросом, без
 * «проверить, потом удалить» (`bookings.pet_id ON DELETE CASCADE` иначе
 * стёр бы историю визитов, ADR-028).
 *
 * @return bool false — Питомца нет/чужой либо у него есть Записи
 */
function petDeleteIfNoBookings(int $userId, int $petId): bool
{
    $stmt = getPdo()->prepare('
        DELETE FROM pets
        WHERE id = ? AND user_id = ?
          AND NOT EXISTS (SELECT 1 FROM bookings WHERE bookings.pet_id = pets.id)
    ');
    $stmt->execute([$petId, $userId]);

    return $stmt->rowCount() > 0;
}
