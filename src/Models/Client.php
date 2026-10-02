<?php

declare(strict_types=1);

/**
 * Модель клиентов для персонала — только SQL через PDO (phase-7.md, Таск 11;
 * FR-MGR-002). Клиент = `users.role = 'customer'`. `$specialistId` ограничивает
 * выборку клиентами с Записью в календаре этого Специалиста
 * (BOOKING_CALENDAR_STATUSES); null — без ограничения (Администратор смены,
 * Владелец).
 */

/**
 * Условие и параметры отбора клиентов по поиску и Специалисту.
 *
 * @param array{name: ?string, phone: ?string}|null $terms clientSearchTerms()
 * @return array{0: string, 1: array<string, int|string>}
 */
function clientWhere(?array $terms, ?int $specialistId): array
{
    $sql = "u.role = 'customer'";
    $params = [];

    if ($terms !== null) {
        $parts = [];
        if ($terms['name'] !== null) {
            $parts[] = 'u.name LIKE :name';
            $params[':name'] = '%' . $terms['name'] . '%';
        }
        if ($terms['phone'] !== null) {
            $parts[] = 'u.phone LIKE :phone';
            $params[':phone'] = '%' . $terms['phone'] . '%';
        }
        if ($parts !== []) {
            $sql .= ' AND (' . implode(' OR ', $parts) . ')';
        }
    }

    if ($specialistId !== null) {
        $placeholders = [];
        foreach (BOOKING_CALENDAR_STATUSES as $index => $status) {
            $placeholders[] = ':status' . $index;
            $params[':status' . $index] = $status;
        }
        $sql .= ' AND EXISTS (SELECT 1 FROM bookings b WHERE b.user_id = u.id'
            . ' AND b.specialist_id = :specialist AND b.status IN (' . implode(',', $placeholders) . '))';
        $params[':specialist'] = $specialistId;
    }

    return [$sql, $params];
}

/** @param array{name: ?string, phone: ?string}|null $terms */
function clientCount(?array $terms, ?int $specialistId): int
{
    [$where, $params] = clientWhere($terms, $specialistId);

    $stmt = getPdo()->prepare("SELECT COUNT(*) FROM users u WHERE {$where}");
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

/**
 * Страница списка клиентов; `pet_names` — клички через запятую.
 *
 * @param array{name: ?string, phone: ?string}|null $terms
 * @return list<array<string, mixed>>
 */
function clientList(?array $terms, ?int $specialistId, int $limit, int $offset): array
{
    [$where, $params] = clientWhere($terms, $specialistId);

    $stmt = getPdo()->prepare("
        SELECT u.id, u.name, u.phone, u.email, u.created_at,
               (SELECT GROUP_CONCAT(p.name ORDER BY p.name SEPARATOR ', ')
                FROM pets p WHERE p.user_id = u.id) AS pet_names
        FROM users u
        WHERE {$where}
        ORDER BY u.name, u.id
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Клиент по id; null — нет такого или это сотрудник.
 *
 * @return array<string, mixed>|null
 */
function clientFind(int $id): ?array
{
    $stmt = getPdo()->prepare(
        "SELECT id, name, phone, email, created_at FROM users WHERE id = ? AND role = 'customer'"
    );
    $stmt->execute([$id]);
    $client = $stmt->fetch();

    return $client === false ? null : $client;
}

/** Есть ли у клиента Запись в календаре этого Специалиста. */
function clientHasBookingWith(int $clientId, int $specialistId): bool
{
    $statuses = BOOKING_CALENDAR_STATUSES;
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));

    $stmt = getPdo()->prepare(
        "SELECT 1 FROM bookings WHERE user_id = ? AND specialist_id = ? AND status IN ({$placeholders}) LIMIT 1"
    );
    $stmt->execute([$clientId, $specialistId, ...$statuses]);

    return $stmt->fetchColumn() !== false;
}

/**
 * Последние визиты клиента (новые первыми). $specialistId — только Записи
 * этого Специалиста.
 *
 * @return list<array<string, mixed>>
 */
function clientVisits(int $clientId, ?int $specialistId, int $limit): array
{
    $statuses = BOOKING_CALENDAR_STATUSES;
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $params = [$clientId, ...$statuses];

    $specialistSql = '';
    if ($specialistId !== null) {
        $specialistSql = 'AND b.specialist_id = ?';
        $params[] = $specialistId;
    }

    $stmt = getPdo()->prepare("
        SELECT b.id, b.scheduled_at, b.status, b.deposit_status,
               p.name AS pet_name, su.name AS specialist_name,
               (SELECT GROUP_CONCAT(bs.service_name ORDER BY bs.sort_order SEPARATOR ', ')
                FROM booking_services bs WHERE bs.booking_id = b.id) AS service_names
        FROM bookings b
        JOIN pets p ON p.id = b.pet_id
        JOIN specialists sp ON sp.id = b.specialist_id
        JOIN users su ON su.id = sp.user_id
        WHERE b.user_id = ? AND b.status IN ({$placeholders}) {$specialistSql}
        ORDER BY b.scheduled_at DESC, b.id DESC
        LIMIT " . $limit . '
    ');
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/**
 * Последние Заказы клиента (новые первыми).
 *
 * @return list<array<string, mixed>>
 */
function clientOrders(int $clientId, int $limit): array
{
    $stmt = getPdo()->prepare('
        SELECT o.id, o.status, o.total, o.created_at,
               (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS items_count
        FROM orders o
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC, o.id DESC
        LIMIT ' . $limit);
    $stmt->execute([$clientId]);

    return $stmt->fetchAll();
}
