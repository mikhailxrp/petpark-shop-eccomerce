<?php

declare(strict_types=1);

/**
 * ИИ-генератор описаний Товаров (FR-AI-002, phase-5 Таск 5). Класс задачи
 * «без ПДн» (BR-AI-001): в промпт уходят только название, Категория, бренд и
 * подтверждённые Характеристики — без цены, остатка, отзывов и прежнего
 * описания. Результат — черновик, на витрину не попадает до публикации.
 */

const DESCRIPTION_MAX_LENGTH = 3000;

const DESCRIPTION_SYSTEM_PROMPT = 'Ты копирайтер зоомагазина. Пиши описание товара по-русски, 2–3 коротких абзаца '
    . 'обычным текстом, без markdown, списков и заголовков. Используй только переданные факты: '
    . 'не выдумывай состав, вес, свойства и страну производства. Не упоминай цену, наличие, акции и отзывы, '
    . 'не давай ветеринарных рекомендаций.';

/**
 * Единственное место, где решается, какие данные Товара уходят провайдеру.
 *
 * @param array{name: string, category_name: string, brand_name: string|null} $product
 * @param array<string, string> $attributes подтверждённые Характеристики: attr_name => значение
 */
function descriptionPrompt(array $product, array $attributes): string
{
    $lines = [
        'Товар: ' . $product['name'],
        'Категория: ' . $product['category_name'],
    ];

    $brand = trim((string) ($product['brand_name'] ?? ''));
    if ($brand !== '') {
        $lines[] = 'Бренд: ' . $brand;
    }

    foreach ($attributes as $name => $value) {
        $lines[] = str_replace('_', ' ', $name) . ': ' . $value;
    }

    return implode("\n", $lines) . "\n\nНапиши описание этого товара для карточки в интернет-магазине.";
}

/** Единый вид текста для хранения и сравнения черновика с правкой: LF, без краёв. */
function descriptionNormalize(string $text): string
{
    return trim(str_replace(["\r\n", "\r"], "\n", $text));
}

/**
 * Очистка ответа ИИ перед сохранением в черновик. Чистая функция.
 * Убирает ограждения кода и markdown-выделение, схлопывает пустые строки,
 * режет по длине. null — после очистки текста нет.
 */
function descriptionClean(string $text): ?string
{
    $clean = descriptionNormalize($text);
    $clean = str_replace(['```', '**', '__'], '', $clean);
    $clean = (string) preg_replace('/^#{1,6}\s+/m', '', $clean);
    $clean = (string) preg_replace("/\n{3,}/", "\n\n", $clean);
    $clean = trim($clean);

    if ($clean === '') {
        return null;
    }

    return mb_substr($clean, 0, DESCRIPTION_MAX_LENGTH);
}

/**
 * Генерация черновика для одного Товара. Статус: ok — text готов к записи;
 * unavailable/blocked — провайдер недоступен или лимит; error — пустой ответ.
 *
 * @param array{id: int, name: string, category_name: string, brand_name: string|null} $product
 * @param array<string, string> $attributes
 * @return array{status: string, text: string}
 */
function descriptionGenerateForProduct(array $product, array $attributes): array
{
    $result = aiComplete(AI_TASK_DESCRIPTION, descriptionPrompt($product, $attributes), DESCRIPTION_SYSTEM_PROMPT);

    if ($result['status'] !== 'ok') {
        return ['status' => $result['status'], 'text' => ''];
    }

    $text = descriptionClean($result['text']);
    if ($text === null) {
        logWarning('AI: пустой ответ генератора описания', ['product_id' => $product['id']]);

        return ['status' => 'error', 'text' => ''];
    }

    return ['status' => 'ok', 'text' => $text];
}
