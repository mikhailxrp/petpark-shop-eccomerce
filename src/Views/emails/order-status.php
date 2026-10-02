<?php

declare(strict_types=1);

/**
 * Письмо о статусе Заказа (FR-NOTIF-001). Фиксированные тексты, без ИИ.
 * Письмо — plain text: htmlspecialchars() дал бы в тексте «&amp;», поэтому
 * значения выводятся как есть.
 *
 * @var array{id: int|string, contact_name: string, total: string, delivery_method: string} $order
 * @var string $status
 * @var bool $refund_note
 */

$headline = match ($status) {
    'new'              => 'Мы приняли ваш заказ и скоро свяжемся для подтверждения.',
    'confirmed'        => 'Заказ подтверждён и передан в работу.',
    'assembled'        => 'Заказ собран.',
    'shipped'          => 'Заказ передан в доставку.',
    'ready_for_pickup' => 'Заказ готов к выдаче — ждём вас в магазине.',
    'delivered'        => 'Заказ доставлен. Спасибо, что выбрали ' . SHOP_NAME . '!',
    'picked_up'        => 'Заказ выдан. Спасибо, что выбрали ' . SHOP_NAME . '!',
    'cancelled'        => 'Заказ отменён.',
};

$lines = [
    'Здравствуйте, ' . $order['contact_name'] . '!',
    '',
    'Заказ №' . (int) $order['id'],
    $headline,
];
if ($refund_note) {
    $lines[] = 'Деньги вернутся на карту.';
}
$lines[] = '';
$lines[] = 'Сумма заказа: ' . cartFormatMoney((string) $order['total']) . ' ₽';
$lines[] = SHOP_NAME;

echo implode("\n", $lines);
