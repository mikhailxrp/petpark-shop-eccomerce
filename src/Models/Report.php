<?php

declare(strict_types=1);

/**
 * Модель отчётов Владельца — только SQL через PDO (php.md).
 * `FR-ADM-004`, `database.md` (`orders`, `order_items`, `bookings`,
 * `booking_services`, `services`). Границы — полуинтервал [from, to),
 * строки `Y-m-d H:i:s`: Заказ в 00:00:00 попадает только в новый период.
 */

/**
 * Выручка и число Заказов по дням; `cancelled` не учитывается.
 *
 * @return array<int, array{day: string, orders_count: int|string, revenue: string}>
 */
function reportOrdersByDay(string $from, string $to): array
{
    $stmt = getPdo()->prepare(
        "SELECT DATE(created_at) AS day, COUNT(*) AS orders_count, SUM(total) AS revenue
         FROM orders
         WHERE status <> 'cancelled' AND created_at >= :from AND created_at < :to
         GROUP BY DATE(created_at)"
    );
    $stmt->execute(['from' => $from, 'to' => $to]);

    return $stmt->fetchAll();
}

/**
 * Самые продаваемые Товары по снэпшоту `order_items.product_name`.
 *
 * @return array<int, array{product_name: string, quantity: int|string, revenue: string}>
 */
function reportTopProducts(string $from, string $to, int $limit): array
{
    $stmt = getPdo()->prepare(
        "SELECT oi.product_name, SUM(oi.quantity) AS quantity, SUM(oi.price * oi.quantity) AS revenue
         FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         WHERE o.status <> 'cancelled' AND o.created_at >= :from AND o.created_at < :to
         GROUP BY oi.product_name
         ORDER BY quantity DESC, revenue DESC, oi.product_name
         LIMIT :limit"
    );
    $stmt->bindValue('from', $from);
    $stmt->bindValue('to', $to);
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Завершённые Записи по виду Услуги; период — по дате визита.
 * Сумма — `booking_services.price` (без Депозитов).
 *
 * @return array<int, array{kind: string|null, bookings_count: int|string, services_count: int|string, revenue: string}>
 */
function reportServicesByKind(string $from, string $to): array
{
    $stmt = getPdo()->prepare(
        "SELECT s.kind, COUNT(DISTINCT b.id) AS bookings_count, COUNT(bs.id) AS services_count, SUM(bs.price) AS revenue
         FROM bookings b
         JOIN booking_services bs ON bs.booking_id = b.id
         LEFT JOIN services s ON s.id = bs.service_id
         WHERE b.status = 'completed' AND b.scheduled_at >= :from AND b.scheduled_at < :to
         GROUP BY s.kind"
    );
    $stmt->execute(['from' => $from, 'to' => $to]);

    return $stmt->fetchAll();
}
