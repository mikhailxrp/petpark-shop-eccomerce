<?php

declare(strict_types=1);

/**
 * ИИ-разбор Характеристик Товара из текста описания (FR-AI-001). Запускается
 * кнопкой в карточке Товара (phase-7 Таск 9). Класс задачи «без ПДн» (BR-AI-001):
 * в промпт уходят только название и описание Товара. Результат — предложение,
 * подставляемое в поля формы: в каталог попадает, только когда Владелец сохранит.
 */

// Характеристики уровня Товара (product_attributes). «Вес упаковки» — уровень Варианта.
const ATTRIBUTE_EXTRACT_NAMES = ['вид_животного', 'возраст', 'назначение'];

const ATTRIBUTE_STATUS_PENDING  = 'pending';        // значение из справочника
const ATTRIBUTE_STATUS_DECISION = 'needs_decision'; // значения нет в справочнике
const ATTRIBUTE_STATUS_EMPTY    = 'empty';          // в тексте не найдено

const ATTRIBUTE_VALUE_MAX_LENGTH = 150; // product_attributes.attr_value

const ATTRIBUTE_SYSTEM_PROMPT = 'Ты извлекаешь характеристики товара зоомагазина из его описания. '
    . 'Не выдумывай: если характеристики нет в тексте — верни null. Отвечай только JSON-объектом.';

/**
 * @param array<string, list<string>> $dictionary attr_name => значения справочника
 * @param list<string> $names
 */
function attributeExtractPrompt(string $name, string $description, array $names, array $dictionary): string
{
    $lines = [];
    foreach ($names as $attrName) {
        $known = $dictionary[$attrName] ?? [];
        $lines[] = $known === []
            ? "- {$attrName}: (справочник пуст, укажи значение из текста)"
            : "- {$attrName}: предпочитай значения из списка: " . implode(', ', $known);
    }

    return "Товар: {$name}\nОписание: {$description}\n\n"
        . "Определи характеристики:\n" . implode("\n", $lines) . "\n\n"
        . 'Верни JSON-объект, где ключи — названия характеристик, а значения — строка или null.';
}

/**
 * Разбор ответа ИИ и сверка со справочником. Чистая функция.
 * Не найдено → empty; есть в справочнике (без учёта регистра) → pending с
 * каноничным написанием; вне справочника → needs_decision.
 *
 * @param list<string> $names
 * @param array<string, list<string>> $dictionary
 * @return array<string, array{value: string|null, status: string}>|null null — ответ не разобран
 */
function attributeDraftsFromResponse(string $text, array $names, array $dictionary): ?array
{
    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start === false || $end === false || $end < $start) {
        return null;
    }

    $data = json_decode(substr($text, $start, $end - $start + 1), true);
    if (!is_array($data)) {
        return null;
    }

    $drafts = [];
    foreach ($names as $name) {
        $raw = $data[$name] ?? null;
        $value = is_string($raw) ? mb_substr(trim($raw), 0, ATTRIBUTE_VALUE_MAX_LENGTH) : '';

        if ($value === '') {
            $drafts[$name] = ['value' => null, 'status' => ATTRIBUTE_STATUS_EMPTY];
            continue;
        }

        $canonical = null;
        foreach ($dictionary[$name] ?? [] as $known) {
            if (mb_strtolower($known) === mb_strtolower($value)) {
                $canonical = $known;
                break;
            }
        }

        $drafts[$name] = $canonical !== null
            ? ['value' => $canonical, 'status' => ATTRIBUTE_STATUS_PENDING]
            : ['value' => $value, 'status' => ATTRIBUTE_STATUS_DECISION];
    }

    return $drafts;
}

/**
 * Разбор одного Товара.
 * ok — предложения готовы; unavailable/blocked — провайдер недоступен или
 * достигнут месячный лимит; error — ответ не разобран.
 *
 * @param array{id: int, name: string, description: string|null} $product
 * @param list<string> $names Характеристики для разбора
 * @param array<string, list<string>> $dictionary
 * @return array{status: string, drafts: array<string, array{value: string|null, status: string}>}
 */
function attributeExtractForProduct(array $product, array $names, array $dictionary): array
{
    $description = trim((string) ($product['description'] ?? ''));

    // Нет текста — разбирать нечего: пустые черновики, вызов ИИ не тратим.
    if ($description === '') {
        return [
            'status' => 'ok',
            'drafts' => array_fill_keys($names, ['value' => null, 'status' => ATTRIBUTE_STATUS_EMPTY]),
        ];
    }

    $result = aiComplete(
        AI_TASK_ATTRIBUTES,
        attributeExtractPrompt((string) $product['name'], $description, $names, $dictionary),
        ATTRIBUTE_SYSTEM_PROMPT
    );

    if ($result['status'] !== 'ok') {
        return ['status' => $result['status'], 'drafts' => []];
    }

    $drafts = attributeDraftsFromResponse($result['text'], $names, $dictionary);
    if ($drafts === null) {
        logWarning('AI: ответ разбора Характеристик не разобран', ['product_id' => $product['id']]);

        return ['status' => 'error', 'drafts' => []];
    }

    return ['status' => 'ok', 'drafts' => $drafts];
}
