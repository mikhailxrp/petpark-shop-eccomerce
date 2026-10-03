<?php

declare(strict_types=1);

/**
 * Модель выгрузки Вариантов на маркетплейсы — только SQL через PDO (php.md).
 * `database.md` (`marketplace_listings`). Демо-заглушка, ADR-001.
 */

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
