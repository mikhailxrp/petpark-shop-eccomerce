<?php

declare(strict_types=1);

/**
 * Сообщение Покупателю по заявке на Возврат (FR-RET-002 п. 3, «Написать
 * покупателю»). Plain text — значения выводятся как есть.
 *
 * @var array{order_id: int|string, contact_name: ?string} $return
 * @var string $message
 */

echo implode("\n", [
    'Здравствуйте, ' . (string) ($return['contact_name'] ?? '') . '!',
    '',
    'Заказ №' . (int) $return['order_id'] . ', заявка на возврат.',
    '',
    $message,
    '',
    'Ответьте на это письмо или позвоните нам.',
    SHOP_NAME,
]);
