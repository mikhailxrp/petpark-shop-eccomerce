<?php

declare(strict_types=1);

/**
 * Чистые правила ИИ-ядра: класс задачи (BR-AI-001), группы помощников,
 * пороги лимита расхода (NFR-AI, §11.8), стоимость по токенам.
 * Без БД и HTTP — покрыто tests/Unit/AiTest.php. Вызов провайдера и журнал —
 * Services/Ai/AiClient.php и Models/AiCall.php.
 *
 * Деньги — целые «доли» в 1/10000 ₽ (как DECIMAL(10,4) в ai_calls.cost),
 * не float (ADR-022).
 */

// Задачи (помощники): значение пишется в ai_calls.task.
const AI_TASK_ATTRIBUTES  = 'attributes';  // FR-AI-001
const AI_TASK_DESCRIPTION = 'description'; // FR-AI-002
const AI_TASK_CONSULTANT  = 'consultant';  // FR-AI-003
const AI_TASK_ORDER_DRAFT = 'order_draft'; // FR-AI-004

const AI_CLASS_ANONYMOUS = 'anonymous'; // без ПДн — допустим любой провайдер
const AI_CLASS_PERSONAL  = 'personal';  // с ПДн — только российский провайдер

// Фоновые помощники без человека на другом конце — отключаются первыми.
const AI_GROUP_BACKGROUND = 'background';
const AI_GROUP_LIVE       = 'live';

// BR-AI-001: класс задачи. Не перечисленная здесь — «с ПДн» (правило 2).
const AI_TASK_CLASSES = [
    AI_TASK_ATTRIBUTES  => AI_CLASS_ANONYMOUS,
    AI_TASK_DESCRIPTION => AI_CLASS_ANONYMOUS,
    AI_TASK_CONSULTANT  => AI_CLASS_PERSONAL,
    AI_TASK_ORDER_DRAFT => AI_CLASS_PERSONAL,
];

const AI_TASK_GROUPS = [
    AI_TASK_ATTRIBUTES  => AI_GROUP_BACKGROUND,
    AI_TASK_DESCRIPTION => AI_GROUP_BACKGROUND,
    AI_TASK_CONSULTANT  => AI_GROUP_LIVE,
    AI_TASK_ORDER_DRAFT => AI_GROUP_LIVE,
];

// Пороги лимита в процентах. 120% в ТЗ не назван — константа (phase-5.md).
const AI_LIMIT_NOTIFY_PERCENT     = 80;  // уведомление Владельцу
const AI_LIMIT_BACKGROUND_PERCENT = 100; // стоп FR-AI-001/002
const AI_LIMIT_LIVE_PERCENT       = 120; // стоп FR-AI-003/004

const AI_MONEY_UNITS = 10000; // долей в одном рубле

// Срок хранения журнала (в месяцах): с ПДн — 6, обезличенные — 12.
const AI_RETENTION_MONTHS = [
    AI_CLASS_PERSONAL  => 6,
    AI_CLASS_ANONYMOUS => 12,
];

function aiTaskClass(?string $task): string
{
    return AI_TASK_CLASSES[$task ?? ''] ?? AI_CLASS_PERSONAL;
}

function aiTaskGroup(?string $task): string
{
    return AI_TASK_GROUPS[$task ?? ''] ?? AI_GROUP_LIVE;
}

/**
 * Имя провайдера для задачи — по настройке класса (.env, BR-AI-001 п. 3).
 *
 * @param array<string, string> $providerByClass класс => имя провайдера
 */
function aiProviderName(?string $task, array $providerByClass): string
{
    $class = aiTaskClass($task);

    return $providerByClass[$class] ?? $providerByClass[AI_CLASS_PERSONAL] ?? 'offline';
}

/** Сумма «12.3456» → доли (123456); лишние знаки отбрасываются. */
function aiMoneyToUnits(string $amount): int
{
    [$whole, $fraction] = array_pad(explode('.', trim($amount), 2), 2, '');
    $fraction = str_pad(substr($fraction, 0, 4), 4, '0');

    return ((int) $whole) * AI_MONEY_UNITS + (int) $fraction;
}

function aiUnitsToMoney(int $units): string
{
    return intdiv($units, AI_MONEY_UNITS) . '.' . str_pad((string) ($units % AI_MONEY_UNITS), 4, '0', STR_PAD_LEFT);
}

/**
 * Стоимость вызова: токены × цена за 1000 токенов, округление вверх до доли.
 *
 * @param string $pricePer1k цена ₽ за 1000 токенов, строкой
 */
function aiCallCost(int $tokens, string $pricePer1k): string
{
    $units = (int) ceil(max(0, $tokens) * aiMoneyToUnits($pricePer1k) / 1000);

    return aiUnitsToMoney($units);
}

/** Доля лимита в процентах (целая часть, вниз). Лимит 0 → «бесконечно исчерпан». */
function aiLimitPercent(string $spent, string $limit): int
{
    $limitUnits = aiMoneyToUnits($limit);
    if ($limitUnits <= 0) {
        return PHP_INT_MAX;
    }

    return intdiv(aiMoneyToUnits($spent) * 100, $limitUnits);
}

/** Разрешена ли задача при данной доле лимита. Блокировка — по группе помощника. */
function aiTaskAllowed(?string $task, int $limitPercent): bool
{
    $threshold = aiTaskGroup($task) === AI_GROUP_BACKGROUND
        ? AI_LIMIT_BACKGROUND_PERCENT
        : AI_LIMIT_LIVE_PERCENT;

    return $limitPercent < $threshold;
}

/** Пора ли уведомить Владельца (80% и выше). */
function aiShouldNotify(int $limitPercent): bool
{
    return $limitPercent >= AI_LIMIT_NOTIFY_PERCENT;
}
