<?php

declare(strict_types=1);

/**
 * Список клиентов — /admin/clients (phase-7.md, Таск 11; FR-MGR-002).
 * Чистая логика без БД: разбор поискового запроса на имя и цифры телефона.
 */

const CLIENT_SEARCH_MAX_LENGTH = 60;
const CLIENT_SEARCH_PHONE_MIN_DIGITS = 3;
const CLIENT_PER_PAGE = 20;
const CLIENT_CARD_LIST_LIMIT = 20;

/** Полный российский номер набран с «8» — в БД он хранится как `+7…` (normalizePhone()). */
const CLIENT_PHONE_FULL_LENGTH = 11;

/**
 * Поисковый запрос → условия для LIKE; null — запроса нет (фильтр не нужен).
 * `name` и `phone` — значения без `%`; `%`/`_`/`\` в `name` экранированы, так что
 * пользовательский ввод не работает как маска. `phone` — только цифры, не короче
 * CLIENT_SEARCH_PHONE_MIN_DIGITS: `+7 (900)`, `8900…` и `900` находят один номер.
 * Запрос без букв и с короткой цифровой частью ищется по имени как есть.
 *
 * @return array{name: ?string, phone: ?string}|null
 */
function clientSearchTerms(mixed $raw): ?array
{
    if (!is_string($raw)) {
        return null;
    }

    $query = trim(mb_substr(trim($raw), 0, CLIENT_SEARCH_MAX_LENGTH));
    if ($query === '') {
        return null;
    }

    $digits = preg_replace('/\D+/', '', $query) ?? '';
    if (strlen($digits) === CLIENT_PHONE_FULL_LENGTH && $digits[0] === '8') {
        $digits = '7' . substr($digits, 1);
    }
    $phone = strlen($digits) >= CLIENT_SEARCH_PHONE_MIN_DIGITS ? $digits : null;

    $hasLetters = preg_match('/\p{L}/u', $query) === 1;
    $name = $hasLetters || $phone === null
        ? addcslashes($query, '\\%_')
        : null;

    return ['name' => $name, 'phone' => $phone];
}

/** Номер страницы из GET: нечисловой → 1, за пределами → в диапазон. */
function clientNormalizePage(mixed $page, int $totalPages): int
{
    if (!is_string($page) || !ctype_digit($page)) {
        return 1;
    }

    return min(max(1, (int) $page), max(1, $totalPages));
}
