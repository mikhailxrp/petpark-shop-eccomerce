<?php

declare(strict_types=1);

/**
 * Модель выгрузки Вариантов на маркетплейсы — только SQL через PDO (php.md).
 * `database.md` (`marketplace_listings`). Демо-заглушка, ADR-001.
 */

/**
 * Число Вариантов для таблицы /admin/marketplaces (все, включая неактивные).
 */
function marketplaceListingCountForAdmin(): int
{
    return (int) getPdo()->query('SELECT COUNT(*) FROM product_variants')->fetchColumn();
}

/**
 * Страница Вариантов с ценой и временем синхронизации по каждой площадке
 * (NULL, если строки в `marketplace_listings` нет). Ключи — `<площадка>_price`
 * и `<площадка>_synced_at` для каждой из MARKETPLACES.
 *
 * @return array<int, array<string, mixed>>
 */
function marketplaceListingListForAdmin(int $limit, int $offset): array
{
    $columns = '';
    $joins = '';
    $params = [];

    foreach (MARKETPLACES as $index => $marketplace) {
        $alias = "ml{$index}";
        $columns .= ", {$alias}.marketplace_price AS {$marketplace}_price, {$alias}.synced_at AS {$marketplace}_synced_at";
        $joins .= " LEFT JOIN marketplace_listings {$alias} ON {$alias}.variant_id = v.id AND {$alias}.marketplace = :mp{$index}";
        $params["mp{$index}"] = $marketplace;
    }

    $stmt = getPdo()->prepare("
        SELECT v.id AS variant_id, v.sku, v.price, v.discount_price, v.stock_quantity,
               v.reserved_quantity, v.is_active AS variant_active,
               p.name, p.is_active AS product_active{$columns}
        FROM product_variants v
        JOIN products p ON p.id = v.product_id{$joins}
        ORDER BY p.name ASC, v.id ASC
        LIMIT :row_limit OFFSET :row_offset
    ");
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value);
    }
    $stmt->bindValue('row_limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue('row_offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Привести `marketplace_listings` одной площадки к текущему каталогу:
 * выгружаемым Вариантам — upsert цены и `synced_at`, остальным — удалить
 * строку («снят с публикации»). Всё в одной транзакции; при сбое
 * таблица остаётся прежней.
 *
 * @return array{listed: int, delisted: int}
 */
function marketplaceListingSync(string $marketplace): array
{
    marketplaceAssertKnown($marketplace);

    $pdo = getPdo();
    $pdo->beginTransaction();

    try {
        $variants = $pdo->query('
            SELECT v.id, v.price, v.discount_price, v.stock_quantity, v.reserved_quantity,
                   v.is_active AS variant_active, p.is_active AS product_active
            FROM product_variants v
            JOIN products p ON p.id = v.product_id
        ')->fetchAll(PDO::FETCH_ASSOC);

        $upsert = $pdo->prepare('
            INSERT INTO marketplace_listings (variant_id, marketplace, marketplace_price, synced_at)
            VALUES (:variant_id, :marketplace, :price, NOW())
            ON DUPLICATE KEY UPDATE marketplace_price = VALUES(marketplace_price), synced_at = NOW()
        ');
        $delete = $pdo->prepare('
            DELETE FROM marketplace_listings WHERE variant_id = :variant_id AND marketplace = :marketplace
        ');

        $listed = 0;
        $delisted = 0;

        foreach ($variants as $variant) {
            $params = ['variant_id' => (int) $variant['id'], 'marketplace' => $marketplace];

            if (marketplaceIsListable(
                (int) $variant['stock_quantity'],
                (int) $variant['reserved_quantity'],
                (bool) $variant['variant_active'],
                (bool) $variant['product_active']
            )) {
                $upsert->execute($params + [
                    'price' => marketplacePrice($variant['price'], $variant['discount_price']),
                ]);
                $listed++;
            } else {
                $delete->execute($params);
                $delisted += $delete->rowCount();
            }
        }

        $pdo->commit();

        return ['listed' => $listed, 'delisted' => $delisted];
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}
