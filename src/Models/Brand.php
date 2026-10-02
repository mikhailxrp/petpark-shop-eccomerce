<?php

declare(strict_types=1);

/**
 * Модель Брендов — только SQL через PDO, возвращает массивы (php.md).
 */

/**
 * Все бренды для выпадающего списка формы Товара.
 *
 * @return array<int, array<string, mixed>>
 */
function brandAll(): array
{
    return getPdo()->query('SELECT id, name FROM brands ORDER BY name')->fetchAll();
}
