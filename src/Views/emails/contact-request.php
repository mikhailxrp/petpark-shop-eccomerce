<?php

declare(strict_types=1);

/**
 * Письмо магазину об обращении с формы `/contacts` (phase-8.md, Таск 3).
 * Plain text: HTML не интерпретируется, поэтому `<script>` из сообщения
 * приходит буквальным текстом; переводы строк в однострочных полях убраны
 * валидацией (contactFormValidate), заголовки письма не затрагиваются.
 *
 * @var array{id: int, name: string, phone: string, email: string, message: string} $request
 */

echo implode("\n", [
    'Новое обращение с сайта (№' . $request['id'] . ')',
    '',
    'Имя: ' . $request['name'],
    'Телефон: ' . $request['phone'],
    'Email: ' . $request['email'],
    '',
    'Сообщение:',
    $request['message'],
    '',
    SHOP_NAME,
]);
