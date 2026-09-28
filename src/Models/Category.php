<?php

declare(strict_types=1);

/**
 * Модель Категорий — только SQL через PDO, возвращает массивы (php.md).
 * Дерево/цепочка родителей — чистые функции в src/Core/Catalog.php.
 */

function categoryAll(): array
{
    return getPdo()->query('
        SELECT id, parent_id, name, slug, sort_order, seo_title, seo_description
        FROM categories
        ORDER BY sort_order, name
    ')->fetchAll();
}
