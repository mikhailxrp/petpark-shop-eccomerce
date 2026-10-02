<?php

declare(strict_types=1);

/**
 * Цветной бейдж статуса Возврата (по образцу order-status-badge.php).
 *
 * @var string $badgeStatus Значение order_returns.status
 */

[$badgeLabel, $badgeClass] = match ($badgeStatus) {
    'submitted' => ['Подана', 'bg-primary'],
    'in_review' => ['На рассмотрении', 'bg-warning'],
    'approved'  => ['Одобрен', 'bg-success'],
    'rejected'  => ['Отклонён', 'bg-danger'],
    'completed' => ['Завершён', 'bg-secondary'],
    default     => [$badgeStatus, 'bg-light text-dark'],
};
?>
<span class="badge <?= e($badgeClass) ?>"><?= e($badgeLabel) ?></span>
