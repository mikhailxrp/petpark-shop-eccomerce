<?php

declare(strict_types=1);

/**
 * Цветной бейдж статуса Заказа (admin-assembly.md, `order-status-badges`).
 * Классы — стандартные цветные плашки Bootstrap/Valex.
 *
 * @var string $badgeStatus Значение orders.status
 */

[$badgeLabel, $badgeClass] = match ($badgeStatus) {
    'new'              => ['Новый', 'bg-primary'],
    'confirmed'        => ['Подтверждён', 'bg-info'],
    'assembled'        => ['Собран', 'bg-secondary'],
    'shipped'          => ['Отправлен', 'bg-warning'],
    'ready_for_pickup' => ['Готов к выдаче', 'bg-warning'],
    'delivered'        => ['Доставлен', 'bg-success'],
    'picked_up'        => ['Выдан', 'bg-success'],
    'cancelled'        => ['Отменён', 'bg-danger'],
    default            => [$badgeStatus, 'bg-light text-dark'],
};
?>
<span class="badge <?= e($badgeClass) ?>"><?= e($badgeLabel) ?></span>
