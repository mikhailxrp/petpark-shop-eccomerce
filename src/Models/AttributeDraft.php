<?php

declare(strict_types=1);

/**
 * Модель черновиков ИИ-разбора Характеристик — только SQL через PDO.
 * `database.md` (`product_attribute_drafts`). В product_attributes не пишет:
 * подтверждение — Таск 4.
 */

/**
 * Условие очереди: активный Товар без единого черновика, у которого
 * подтверждены не все запрашиваемые Характеристики. Плейсхолдеры: N имён + N.
 */
function attributeDraftQueueWhere(int $namesCount): string
{
    $placeholders = implode(',', array_fill(0, $namesCount, '?'));

    return "p.is_active = 1
        AND NOT EXISTS (SELECT 1 FROM product_attribute_drafts d WHERE d.product_id = p.id)
        AND (SELECT COUNT(DISTINCT pa.attr_name) FROM product_attributes pa
             WHERE pa.product_id = p.id AND pa.attr_name IN ({$placeholders})) < {$namesCount}";
}

/**
 * Очередь необработанных Товаров порцией (id > $afterId — курсор, чтобы Товар
 * с ошибкой не брался повторно в том же запуске).
 *
 * @param list<string> $names
 * @return array<int, array{id: int, name: string, description: string|null}>
 */
function attributeDraftQueue(array $names, int $afterId, int $limit): array
{
    $stmt = getPdo()->prepare(
        'SELECT p.id, p.name, p.description FROM products p
         WHERE p.id > ? AND ' . attributeDraftQueueWhere(count($names)) . '
         ORDER BY p.id LIMIT ?'
    );
    $position = 1;
    $stmt->bindValue($position++, $afterId, PDO::PARAM_INT);
    foreach ($names as $name) {
        $stmt->bindValue($position++, $name);
    }
    $stmt->bindValue($position, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/** @param list<string> $names */
function attributeDraftQueueCount(array $names): int
{
    $stmt = getPdo()->prepare(
        'SELECT COUNT(*) FROM products p WHERE ' . attributeDraftQueueWhere(count($names))
    );
    $stmt->execute($names);

    return (int) $stmt->fetchColumn();
}

/**
 * Справочник Характеристик — значения, уже встречающиеся в каталоге.
 *
 * @param list<string> $names
 * @return array<string, list<string>> attr_name => значения
 */
function attributeDictionary(array $names): array
{
    $placeholders = implode(',', array_fill(0, count($names), '?'));
    $stmt = getPdo()->prepare(
        "SELECT DISTINCT attr_name, attr_value FROM product_attributes
         WHERE attr_name IN ({$placeholders}) ORDER BY attr_name, attr_value"
    );
    $stmt->execute($names);

    $dictionary = array_fill_keys($names, []);
    foreach ($stmt->fetchAll() as $row) {
        $dictionary[(string) $row['attr_name']][] = (string) $row['attr_value'];
    }

    return $dictionary;
}

/**
 * Какие из запрашиваемых Характеристик у Товара ещё не подтверждены.
 *
 * @param list<string> $names
 * @return list<string>
 */
function attributeMissingNames(int $productId, array $names): array
{
    $placeholders = implode(',', array_fill(0, count($names), '?'));
    $stmt = getPdo()->prepare(
        "SELECT DISTINCT attr_name FROM product_attributes
         WHERE product_id = ? AND attr_name IN ({$placeholders})"
    );
    $stmt->execute([$productId, ...$names]);
    $confirmed = $stmt->fetchAll(PDO::FETCH_COLUMN);

    return array_values(array_diff($names, $confirmed));
}

/**
 * Все черновики одного Товара — атомарно. INSERT IGNORE: при гонке двух
 * вкладок остаётся первый результат, дублей нет (UNIQUE product_id+attr_name).
 *
 * @param array<string, array{value: string|null, status: string}> $drafts attr_name => черновик
 */
function attributeDraftsSave(int $productId, array $drafts): void
{
    $pdo = getPdo();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO product_attribute_drafts (product_id, attr_name, attr_value, status)
             VALUES (?, ?, ?, ?)'
        );
        foreach ($drafts as $name => $draft) {
            $stmt->execute([$productId, $name, $draft['value'], $draft['status']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** @return array{processed: int, pending: int, needs_decision: int} */
function attributeDraftCounts(): array
{
    $row = getPdo()->query(
        "SELECT COUNT(DISTINCT product_id) AS processed,
                COALESCE(SUM(status = 'pending'), 0) AS pending,
                COALESCE(SUM(status = 'needs_decision'), 0) AS needs_decision
         FROM product_attribute_drafts"
    )->fetch();

    return [
        'processed'      => (int) $row['processed'],
        'pending'        => (int) $row['pending'],
        'needs_decision' => (int) $row['needs_decision'],
    ];
}
