<?php

declare(strict_types=1);

/**
 * Валидация формы обратной связи на `/contacts` (phase-8.md, Таск 3). Чистая
 * функция без БД и HTTP — покрыта tests/Unit/ContactFormTest.php. Контроллер
 * только передаёт сырой ввод и решает, что делать с результатом.
 */

const CONTACT_NAME_MAX = 100;
const CONTACT_EMAIL_MAX = 255;
const CONTACT_MESSAGE_MAX = 2000;

const CONTACT_ERROR_REQUIRED = 'Заполните это поле.';
const CONTACT_ERROR_TOO_LONG = 'Слишком длинное значение.';
const CONTACT_ERROR_PHONE = 'Укажите телефон в формате +7 900 000-00-00.';
const CONTACT_ERROR_EMAIL = 'Укажите корректный email, например name@example.ru.';

/**
 * @param array<string, mixed> $input name, phone, email, message (сырой ввод)
 * @return array{
 *     values: array{name: string, phone: string, email: string, message: string},
 *     errors: array<string, string>
 * } values — очищенные значения (телефон в `+7XXXXXXXXXX`, если распознан);
 *   errors — по полю, пусто = форма верна
 */
function contactFormValidate(array $input): array
{
    $values = [
        'name'    => contactFormSingleLine($input['name'] ?? ''),
        'phone'   => contactFormSingleLine($input['phone'] ?? ''),
        'email'   => contactFormSingleLine($input['email'] ?? ''),
        'message' => trim(mb_scrub(is_string($input['message'] ?? null) ? $input['message'] : '')),
    ];
    $errors = [];

    if ($values['name'] === '') {
        $errors['name'] = CONTACT_ERROR_REQUIRED;
    } elseif (mb_strlen($values['name']) > CONTACT_NAME_MAX) {
        $errors['name'] = CONTACT_ERROR_TOO_LONG;
    }

    if ($values['phone'] === '') {
        $errors['phone'] = CONTACT_ERROR_REQUIRED;
    } else {
        $phone = normalizePhone($values['phone']);
        if ($phone === null) {
            $errors['phone'] = CONTACT_ERROR_PHONE;
        } else {
            $values['phone'] = $phone;
        }
    }

    if ($values['email'] === '') {
        $errors['email'] = CONTACT_ERROR_REQUIRED;
    } elseif (
        mb_strlen($values['email']) > CONTACT_EMAIL_MAX
        || filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false
        || !str_contains(substr($values['email'], (int) strrpos($values['email'], '@')), '.')
    ) {
        $errors['email'] = CONTACT_ERROR_EMAIL;
    }

    if ($values['message'] === '') {
        $errors['message'] = CONTACT_ERROR_REQUIRED;
    } elseif (mb_strlen($values['message']) > CONTACT_MESSAGE_MAX) {
        $errors['message'] = CONTACT_ERROR_TOO_LONG;
    }

    return ['values' => $values, 'errors' => $errors];
}

/** Однострочное поле: не-строка → '', управляющие символы (в т.ч. переводы строк) убираются. */
function contactFormSingleLine(mixed $raw): string
{
    if (!is_string($raw)) {
        return '';
    }

    return trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', mb_scrub($raw)));
}
