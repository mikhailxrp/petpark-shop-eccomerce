<?php

declare(strict_types=1);

/**
 * Вырезание телефона и адреса из текста Обращения (FR-AI-004, FR-CHANNELS-004,
 * BR-AI-001; phase-5.md, Таск 9). Чистые функции без БД и HTTP — покрыто
 * tests/Unit/PiiTest.php. Имя не фильтруется. Применяется до отправки текста
 * провайдеру и повторно к его ответу (Q-038).
 */

const PII_PHONE_PLACEHOLDER   = '[телефон скрыт]';
const PII_ADDRESS_PLACEHOLDER = '[адрес скрыт]';

/** Российский номер: +7 / 7 / 8 или без кода страны, любые пробелы, скобки и дефисы между группами. */
const PII_PHONE_PATTERN = '/(?<!\d)(?:\+?[78][\s\-()]*)?\(?\d{3}\)?[\s\-]*\d{3}[\s\-]*\d{2}[\s\-]*\d{2}(?!\d)/u';

/** Улица/проспект/переулок и т.п. + название (одно слово, второе — только с заглавной или номер). */
const PII_STREET_PATTERN = '/(?<!\p{L})(?i:ул|улиц[аеуы]|пр-т|просп|проспект|пер|переулок|б-р|бульвар|ш|шоссе|наб|набережная|пл|площадь|мкр|микрорайон)'
    . '(?:\.|(?=\s))\s*[\p{L}\d\-]+(?:\s+(?:\p{Lu}[\p{L}\-]*|\d+[\p{L}\-]*))?/u';

/** Дом, корпус, строение, квартира, офис, подъезд, этаж с номером. */
const PII_HOUSE_PATTERN = '/(?<!\p{L})(?i:д\.|дом|корп\.?|корпус|стр\.|кв\.?|квартира|оф\.?|офис|подъезд|этаж)\s*\d+[\p{L}\/\-\d]*/u';

function piiStripPhones(string $text): string
{
    return (string) preg_replace(PII_PHONE_PATTERN, PII_PHONE_PLACEHOLDER, $text);
}

function piiStripAddress(string $text): string
{
    $text = (string) preg_replace([PII_STREET_PATTERN, PII_HOUSE_PATTERN], PII_ADDRESS_PLACEHOLDER, $text);

    // «ул. Ленина, д. 5, кв. 12» даёт несколько меток подряд — оставляем одну.
    $placeholder = preg_quote(PII_ADDRESS_PLACEHOLDER, '/');

    return (string) preg_replace('/' . $placeholder . '(?:[\s,;]*' . $placeholder . ')+/u', PII_ADDRESS_PLACEHOLDER, $text);
}

/** Телефон и адрес вырезаны, имя и остальной текст остались. */
function piiRedact(string $text): string
{
    return piiStripAddress(piiStripPhones($text));
}
