<?php

declare(strict_types=1);

/**
 * Модель Услуг и Специалистов для формы Записи — только SQL через PDO,
 * возвращает массивы (php.md). `FR-SV-001`, `FR-SV-002`, `database.md`
 * (`services`, `specialists`, `specialist_services`).
 */

/**
 * @return array<int, array<string, mixed>>
 */
function servicesActive(): array
{
    return getPdo()->query(
        'SELECT id, name, kind, duration_minutes, price, deposit_amount
         FROM services
         WHERE is_active = 1
         ORDER BY kind, id'
    )->fetchAll();
}

/**
 * Активные Услуги с slug для публичного списка `/services`. Услуга без slug
 * (заведена вне сида) страницы не имеет и в список не попадает.
 *
 * @return array<int, array<string, mixed>>
 */
function servicesPublicList(): array
{
    return getPdo()->query(
        'SELECT id, name, slug, kind, duration_minutes, price, deposit_amount, description
         FROM services
         WHERE is_active = 1 AND slug IS NOT NULL
         ORDER BY kind, id'
    )->fetchAll();
}

/**
 * Активная Услуга по slug для `/services/{slug}`; null — нет такой или снята.
 *
 * @return array<string, mixed>|null
 */
function serviceFindActiveBySlug(string $slug): ?array
{
    $stmt = getPdo()->prepare(
        'SELECT id, name, slug, kind, duration_minutes, price, deposit_amount, description
         FROM services
         WHERE is_active = 1 AND slug = :slug'
    );
    $stmt->execute(['slug' => $slug]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Активные Услуги по id, в порядке возрастания id. Несуществующие и
 * снятые с продажи в результат не попадают — вызывающий сравнивает длину.
 *
 * @param list<int> $ids
 * @return array<int, array<string, mixed>>
 */
function servicesFindActiveByIds(array $ids): array
{
    if ($ids === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = getPdo()->prepare(
        "SELECT id, name, kind, duration_minutes, price, deposit_amount
         FROM services
         WHERE is_active = 1 AND id IN ({$placeholders})
         ORDER BY id"
    );
    $stmt->execute($ids);

    return $stmt->fetchAll();
}

/**
 * Специалисты, оказывающие ВСЕ перечисленные Услуги (FR-SV-002): HAVING по
 * числу различных Услуг, а не «хотя бы одну». Отключённый сотрудник
 * (`users.is_active = 0`, ADR-033) и сотрудник, сменивший роль с
 * `specialist`, в выборку не попадают — к ним нельзя записаться и перенести
 * Запись; уже оформленные Записи это не затрагивает.
 *
 * @param list<int> $serviceIds уникальные id
 * @return array<int, array<string, mixed>> id, name, work_start, work_end, day_off
 */
function specialistsForServices(array $serviceIds): array
{
    if ($serviceIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
    $stmt = getPdo()->prepare(
        "SELECT sp.id, u.name, sp.work_start, sp.work_end, sp.day_off
         FROM specialists sp
         JOIN users u ON u.id = sp.user_id
         JOIN specialist_services ss ON ss.specialist_id = sp.id
         WHERE ss.service_id IN ({$placeholders})
           AND u.is_active = 1 AND u.role = 'specialist'
         GROUP BY sp.id, u.name, sp.work_start, sp.work_end, sp.day_off
         HAVING COUNT(DISTINCT ss.service_id) = ?
         ORDER BY u.name, sp.id"
    );
    $stmt->execute([...$serviceIds, count($serviceIds)]);

    return $stmt->fetchAll();
}
