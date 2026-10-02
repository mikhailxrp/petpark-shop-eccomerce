<?php

declare(strict_types=1);

/**
 * Справочник Характеристик Товара — только SQL через PDO. Используется ИИ-разбором
 * в карточке Товара (FR-AI-001, phase-7 Таск 9): подсказки для полей и промпт ИИ.
 * Таблица `product_attribute_drafts` с Таска 9 не используется (`database.md`).
 */

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
