<?php

declare(strict_types=1);

/**
 * ИИ-разбор Характеристик Товара из текста описания (FR-AI-001, phase-5 Таск 3).
 * Класс задачи «без ПДн» (BR-AI-001): в промпт уходят только название и
 * описание Товара. Результат — черновики, в каталог сами не попадают.
 */

// Характеристики уровня Товара (product_attributes). «Вес упаковки» — уровень Варианта.
const ATTRIBUTE_EXTRACT_NAMES = ['вид_животного', 'возраст', 'назначение'];

const ATTRIBUTE_STATUS_PENDING  = 'pending';        // значение из справочника, ждёт подтверждения
const ATTRIBUTE_STATUS_DECISION = 'needs_decision'; // значения нет в справочнике
const ATTRIBUTE_STATUS_EMPTY    = 'empty';          // в тексте не найдено

const ATTRIBUTE_STATUS_CONFIRMED = 'confirmed';     // Владелец подтвердил/поправил
const ATTRIBUTE_STATUS_REJECTED  = 'rejected';      // Владелец отклонил

const ATTRIBUTE_VALUE_MAX_LENGTH = 150; // product_attributes.attr_value

const ATTRIBUTE_ACTION_CONFIRM = 'confirm';
const ATTRIBUTE_ACTION_EDIT    = 'edit';
const ATTRIBUTE_ACTION_REJECT  = 'reject';

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
 * Проверка решения Владельца по черновику. Чистая функция.
 * confirm — значение черновика как есть (outcome accepted); edit — значение
 * из формы, 1–150 символов (edited); reject — ничего не пишется (rejected).
 *
 * @return array{error: string|null, value: string|null, outcome: string|null}
 */
function attributeDecisionResolve(string $action, ?string $draftValue, string $input): array
{
    $fail = static fn (string $message): array => ['error' => $message, 'value' => null, 'outcome' => null];

    return match ($action) {
        ATTRIBUTE_ACTION_CONFIRM => ($draftValue === null || trim($draftValue) === '')
            ? $fail('У черновика нет значения — подтверждать нечего.')
            : ['error' => null, 'value' => $draftValue, 'outcome' => 'accepted'],
        ATTRIBUTE_ACTION_EDIT => (static function () use ($input, $fail): array {
            $value = trim($input);
            if ($value === '') {
                return $fail('Укажите значение Характеристики.');
            }
            if (mb_strlen($value) > ATTRIBUTE_VALUE_MAX_LENGTH) {
                return $fail('Значение не длиннее ' . ATTRIBUTE_VALUE_MAX_LENGTH . ' символов.');
            }

            return ['error' => null, 'value' => $value, 'outcome' => 'edited'];
        })(),
        ATTRIBUTE_ACTION_REJECT => ['error' => null, 'value' => null, 'outcome' => 'rejected'],
        default => $fail('Неизвестное действие.'),
    };
}

/**
 * Разбор одного Товара.
 * ok — черновики готовы к записи; unavailable/blocked — провайдер недоступен
 * или лимит, Товар остаётся в очереди; error — ответ не разобран, Товар
 * остаётся в очереди, но пакет можно продолжать.
 *
 * @param array{id: int, name: string, description: string|null} $product
 * @param list<string> $names Характеристики, которых у Товара ещё нет
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
