<?php

declare(strict_types=1);

/**
 * Письмо о статусе Возврата (FR-RET-001–003). Фиксированные тексты, без ИИ.
 * Письмо — plain text: htmlspecialchars() дал бы в тексте «&amp;», поэтому
 * значения выводятся как есть.
 *
 * @var array{id: int|string, order_id: int|string, contact_name: ?string, total: string, decision_comment: ?string} $return
 * @var string $status
 */

$headline = match ($status) {
    'submitted' => 'Мы получили вашу заявку на возврат и рассмотрим её в течение 24 часов.',
    'in_review' => 'Заявка на возврат взята в работу.',
    'approved'  => 'Заявка на возврат одобрена.',
    'rejected'  => 'К сожалению, заявка на возврат отклонена.',
    'completed' => 'Возврат завершён.',
};

$lines = [
    'Здравствуйте, ' . (string) ($return['contact_name'] ?? '') . '!',
    '',
    'Заказ №' . (int) $return['order_id'],
    $headline,
];

$comment = trim((string) ($return['decision_comment'] ?? ''));
if ($comment !== '' && in_array($status, ['approved', 'rejected'], true)) {
    $lines[] = '';
    $lines[] = ($status === 'rejected' ? 'Причина: ' : 'Дальнейшие шаги: ') . $comment;
}

$lines[] = '';
$lines[] = 'Сумма заказа: ' . cartFormatMoney((string) $return['total']) . ' ₽';
$lines[] = SHOP_NAME;

echo implode("\n", $lines);
