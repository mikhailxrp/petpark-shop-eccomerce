<?php

declare(strict_types=1);

/**
 * Чистые правила Возврата (FR-RET-001–003): переходы статусов, «Заказ можно
 * вернуть», лимиты фото, темы писем. Без БД и HTTP — покрыто
 * tests/Unit/OrderReturnTest.php. Данные — Models/OrderReturn.php.
 */

/**
 * Допустимые переходы order_returns.status — диаграмма «Возврат», tz.md §6.3.
 * rejected/completed — конечные.
 */
const RETURN_STATUS_TRANSITIONS = [
    'submitted' => ['in_review'],
    'in_review' => ['approved', 'rejected'],
    'approved'  => ['completed'],
    'rejected'  => [],
    'completed' => [],
];

// Решение по заявке (одобрить/отказать) требует комментария покупателю.
const RETURN_STATUSES_REQUIRING_COMMENT = ['approved', 'rejected'];

// Вернуть можно только уже полученный Заказ (tz.md §6.1 «Возврат»).
const RETURN_ORDER_STATUSES = ['delivered', 'picked_up'];

const RETURN_PHOTOS_MIN = 1;
const RETURN_PHOTOS_MAX = 5;
const RETURN_PHOTO_MAX_BYTES = 5 * 1024 * 1024;

// MIME (по содержимому файла) → расширение, под которым файл сохраняется.
const RETURN_PHOTO_MIME_EXTENSIONS = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

// Хвост темы письма после «Возврат по заказу №N — ».
const RETURN_STATUS_SUBJECTS = [
    'submitted' => 'заявка принята',
    'in_review' => 'заявка взята в работу',
    'approved'  => 'заявка одобрена',
    'rejected'  => 'заявка отклонена',
    'completed' => 'возврат завершён',
];

function returnCanTransition(string $from, string $to): bool
{
    return in_array($to, RETURN_STATUS_TRANSITIONS[$from] ?? [], true);
}

function returnTransitionRequiresComment(string $to): bool
{
    return in_array($to, RETURN_STATUSES_REQUIRING_COMMENT, true);
}

/**
 * Как вернуть деньги при завершении Возврата (FR-RET-003): `card` — через
 * шлюз, `cash` — отметка «возвращено наличными» (оплата при получении),
 * `none` — денег не было или уже возвращены.
 *
 * @return 'card'|'cash'|'none'
 */
function returnRefundKind(string $paymentMethod, string $paymentStatus): string
{
    if ($paymentStatus !== 'paid') {
        return 'none';
    }

    return match ($paymentMethod) {
        'card_online'              => 'card',
        'cash_or_card_on_delivery' => 'cash',
        default                    => 'none',
    };
}

/**
 * Можно ли подать заявку на Заказ: он получен и заявки на него ещё нет
 * (0..1 Возврат на Заказ, order_returns.order_id UNIQUE).
 */
function returnOrderCanBeReturned(string $orderStatus, bool $hasReturn): bool
{
    return !$hasReturn && in_array($orderStatus, RETURN_ORDER_STATUSES, true);
}
