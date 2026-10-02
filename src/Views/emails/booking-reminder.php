<?php

declare(strict_types=1);

/**
 * Напоминание о Записи за 3 часа (FR-NOTIF-001). Фиксированный текст, без ИИ;
 * plain text — значения выводятся без htmlspecialchars().
 *
 * @var array<string, mixed> $booking bookingNotificationData()
 */

$when = (new DateTimeImmutable((string) $booking['scheduled_at']))->format('d.m.Y в H:i');

$lines = [
    'Здравствуйте, ' . $booking['customer_name'] . '!',
    '',
    'Напоминаем: скоро ваш визит.',
    '',
    'Дата и время: ' . $when,
    'Питомец: ' . $booking['pet_name'],
    'Специалист: ' . $booking['specialist_name'],
    'Услуги:',
];
foreach ($booking['services'] as $service) {
    $lines[] = '— ' . $service['service_name'] . ', ' . cartFormatMoney((string) $service['price']) . ' ₽';
}
if ($booking['deposit_status'] === 'held' && $booking['deposit_amount'] !== null) {
    $lines[] = '';
    $lines[] = 'Депозит: ' . cartFormatMoney((string) $booking['deposit_amount']) . ' ₽';
}
$lines[] = '';
$lines[] = SHOP_NAME;

echo implode("\n", $lines);
