<?php

declare(strict_types=1);

/**
 * Цветной бейдж статуса Записи (по образцу order-status-badge.php).
 * `slot_released` в админке не показывается, но на всякий случай — в default.
 *
 * @var string $badgeStatus Значение bookings.status
 */

[$badgeLabel, $badgeClass] = match ($badgeStatus) {
    'slot_selected' => ['Ждёт оплаты', 'bg-warning'],
    'confirmed'     => ['Подтверждена', 'bg-info'],
    'completed'     => ['Завершена', 'bg-success'],
    'no_show'       => ['Неявка', 'bg-secondary'],
    'cancelled'     => ['Отменена', 'bg-danger'],
    default         => [$badgeStatus, 'bg-light text-dark'],
};
?>
<span class="badge <?= e($badgeClass) ?>"><?= e($badgeLabel) ?></span>
