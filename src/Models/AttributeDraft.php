<?php

declare(strict_types=1);

/**
 * Модель черновиков ИИ-разбора Характеристик — только SQL через PDO.
 * `database.md` (`product_attribute_drafts`). В product_attributes пишет только
 * attributeDraftDecide() — решение Владельца (Таск 4).
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

/** Товаров с нерешёнными черновиками (pending / needs_decision). */
function attributeDraftOpenProductCount(): int
{
    return (int) getPdo()->query(
        "SELECT COUNT(DISTINCT product_id) FROM product_attribute_drafts
         WHERE status IN ('pending', 'needs_decision')"
    )->fetchColumn();
}

/**
 * Страница Товаров с нерешёнными черновиками — пагинация по Товарам, чтобы
 * группа не рвалась между страницами.
 *
 * @return array<int, array{id: int, name: string}>
 */
function attributeDraftOpenProducts(int $limit, int $offset): array
{
    $stmt = getPdo()->prepare(
        "SELECT p.id, p.name FROM products p
         WHERE EXISTS (SELECT 1 FROM product_attribute_drafts d
                       WHERE d.product_id = p.id AND d.status IN ('pending', 'needs_decision'))
         ORDER BY p.id LIMIT ? OFFSET ?"
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Нерешённые черновики указанных Товаров.
 *
 * @param list<int> $productIds
 * @return array<int, array{id: int, product_id: int, attr_name: string, attr_value: string|null, status: string}>
 */
function attributeDraftsOpenForProducts(array $productIds): array
{
    if ($productIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = getPdo()->prepare(
        "SELECT id, product_id, attr_name, attr_value, status FROM product_attribute_drafts
         WHERE status IN ('pending', 'needs_decision') AND product_id IN ({$placeholders})
         ORDER BY product_id, id"
    );
    $stmt->execute($productIds);

    return $stmt->fetchAll();
}

/**
 * Решение Владельца — атомарно: Характеристика в product_attributes (если не
 * отклонено) + статус черновика + исход. Решается только открытый черновик
 * (FOR UPDATE + проверка статуса): повтор той же формы вернёт false и не
 * создаст ни дубля Характеристики, ни второго исхода.
 *
 * @param string|null $value итоговое значение; null — отклонение
 * @return bool false — черновика нет или он уже решён
 */
function attributeDraftDecide(int $draftId, ?string $value, string $outcome): bool
{
    $pdo = getPdo();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "SELECT product_id, attr_name FROM product_attribute_drafts
             WHERE id = ? AND status IN ('pending', 'needs_decision') FOR UPDATE"
        );
        $stmt->execute([$draftId]);
        $draft = $stmt->fetch();

        if ($draft === false) {
            $pdo->rollBack();

            return false;
        }

        if ($value !== null) {
            productAttributeReplace((int) $draft['product_id'], (string) $draft['attr_name'], $value);
        }

        $pdo->prepare('UPDATE product_attribute_drafts SET status = ?, attr_value = COALESCE(?, attr_value) WHERE id = ?')
            ->execute([$value !== null ? ATTRIBUTE_STATUS_CONFIRMED : ATTRIBUTE_STATUS_REJECTED, $value, $draftId]);
        $pdo->prepare("INSERT INTO ai_draft_outcomes (kind, ref_id, outcome) VALUES ('attributes', ?, ?)")
            ->execute([$draftId, $outcome]);

        $pdo->commit();

        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Значение нерешённого черновика; null — черновика нет, он решён или пуст. */
function attributeDraftOpenValue(int $draftId): ?string
{
    $stmt = getPdo()->prepare(
        "SELECT attr_value FROM product_attribute_drafts
         WHERE id = ? AND status IN ('pending', 'needs_decision')"
    );
    $stmt->execute([$draftId]);
    $value = $stmt->fetchColumn();

    return is_string($value) ? $value : null;
}
