<?php

declare(strict_types=1);

/**
 * Карточка заявки на Возврат — /admin/returns/{id} (phase-6.md, Таск 6;
 * FR-RET-002, FR-RET-003).
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var array<string, mixed> $return returnFindForAdmin()
 * @var list<string> $photos пути фото относительно public/
 * @var bool $canReview доступно «Взять в работу»
 * @var bool $canDecide доступны «Одобрить»/«Отклонить»
 * @var bool $canMessage доступно «Написать покупателю»
 * @var bool $canComplete доступно «Завершить возврат»
 * @var string $refundKind returnRefundKind(): card|cash|none
 * @var int $commentMax лимит длины комментария/сообщения
 * @var string|null $success
 * @var string|null $error
 */

$orderStatusLabel = match ($return['order_status']) {
    'delivered'  => 'Доставлен',
    'picked_up'  => 'Выдан',
    default      => (string) $return['order_status'],
};
$paymentLabel = match ($return['payment_method']) {
    'card_online'              => 'Картой на сайте',
    'cash_or_card_on_delivery' => 'Наличными или картой при получении',
    default                    => (string) $return['payment_method'],
};
$paymentStatusLabel = match ($return['payment_status']) {
    'paid'     => 'Оплачен',
    'refunded' => 'Возвращён',
    default    => 'Ожидает оплаты',
};
$returnId = (int) $return['id'];

ob_start();
?>
<?php if ($success !== null): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= e($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"><i class="fe fe-x" aria-hidden="true"></i></button>
    </div>
<?php endif; ?>
<?php if ($error !== null): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"><i class="fe fe-x" aria-hidden="true"></i></button>
    </div>
<?php endif; ?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4">
    <div>
        <h1 class="mb-0">Возврат по заказу №<?= (int) $return['order_id'] ?></h1>
        <p class="mb-0 text-muted">
            Подана <?= e(date('d.m.Y H:i', (int) strtotime((string) $return['created_at']))) ?>
        </p>
    </div>
    <div class="mt-3 mt-md-0">
        <?php $badgeStatus = (string) $return['status']; ?>
        <?php include __DIR__ . '/../components/admin/return-status-badge.php'; ?>
        <a href="/admin/returns" class="btn btn-outline-secondary btn-sm ms-2">К списку</a>
    </div>
</div>

<div class="row">
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Покупатель</h2></div>
            <div class="card-body">
                <dl class="mb-0">
                    <dt>Имя</dt>
                    <dd><?= e((string) ($return['contact_name'] ?? '')) ?></dd>
                    <dt>Телефон</dt>
                    <dd><?= e((string) ($return['contact_phone'] ?? '')) ?></dd>
                    <dt>Email</dt>
                    <dd class="mb-0"><?= e((string) ($return['contact_email'] ?? '')) ?></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Заказ</h2></div>
            <div class="card-body">
                <dl class="mb-0">
                    <dt>Номер</dt>
                    <dd><a href="/admin/orders/<?= (int) $return['order_id'] ?>">№<?= (int) $return['order_id'] ?></a></dd>
                    <dt>Статус Заказа</dt>
                    <dd><?= e($orderStatusLabel) ?></dd>
                    <dt>Сумма, ₽</dt>
                    <dd><?= e(cartFormatMoney((string) $return['total'])) ?></dd>
                    <dt>Оплата</dt>
                    <dd class="mb-0"><?= e($paymentLabel) ?> — <?= e($paymentStatusLabel) ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Причина и фото</h2></div>
    <div class="card-body">
        <p class="text-break"><?= nl2br(e((string) $return['reason'])) ?></p>
        <?php if ($photos === []): ?>
            <p class="mb-0 text-muted">Фото нет.</p>
        <?php else: ?>
            <div class="row g-2">
                <?php foreach ($photos as $index => $path): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="/<?= e($path) ?>" target="_blank" rel="noopener">
                            <img src="/<?= e($path) ?>" alt="Фото к заявке на возврат по заказу №<?= (int) $return['order_id'] ?>, <?= $index + 1 ?> из <?= count($photos) ?>" class="img-fluid rounded" loading="lazy">
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (($return['decision_comment'] ?? null) !== null): ?>
    <div class="card">
        <div class="card-header"><h2 class="card-title">Решение</h2></div>
        <div class="card-body">
            <p class="text-break mb-1"><?= nl2br(e((string) $return['decision_comment'])) ?></p>
            <?php if (($return['resolved_by_name'] ?? null) !== null): ?>
                <p class="mb-0 text-muted fs-12">Решение принял: <?= e((string) $return['resolved_by_name']) ?></p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h2 class="card-title">Действия</h2></div>
    <div class="card-body">
        <?php if (!$canReview && !$canDecide && !$canMessage && !$canComplete): ?>
            <p class="mb-0 text-muted">Для этой заявки действий нет.</p>
        <?php endif; ?>

        <?php if ($canReview): ?>
            <form method="post" action="/admin/returns/<?= $returnId ?>/review">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-primary">Взять в работу</button>
            </form>
        <?php endif; ?>

        <?php if ($canDecide): ?>
            <form method="post" action="/admin/returns/<?= $returnId ?>/approve" class="mb-3">
                <?= csrfField() ?>
                <label for="return-comment" class="form-label">Комментарий Покупателю (дальнейшие шаги или причина отказа)</label>
                <textarea id="return-comment" name="comment" class="form-control" rows="3" maxlength="<?= $commentMax ?>" required></textarea>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <button type="submit" class="btn btn-success">Одобрить</button>
                    <button type="submit" formaction="/admin/returns/<?= $returnId ?>/reject" class="btn btn-outline-danger">Отклонить</button>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($canComplete): ?>
            <?php
            $completeHint = match ($refundKind) {
                'card'  => 'Деньги (' . cartFormatMoney((string) $return['total']) . ' ₽) вернутся на карту Покупателя, остаток вернётся на склад, Покупатель получит письмо.',
                'cash'  => 'Заказ будет отмечен «возвращено наличными» — выдайте деньги Покупателю. Остаток вернётся на склад, Покупатель получит письмо.',
                default => 'Денег к возврату нет (заказ не оплачен или уже возвращён). Остаток вернётся на склад, Покупатель получит письмо.',
            };
            ?>
            <form method="post" action="/admin/returns/<?= $returnId ?>/complete" class="mb-3">
                <?= csrfField() ?>
                <p><?= e($completeHint) ?></p>
                <button type="submit" class="btn btn-primary">Завершить возврат</button>
            </form>
        <?php endif; ?>

        <?php if ($canMessage): ?>
            <form method="post" action="/admin/returns/<?= $returnId ?>/message">
                <?= csrfField() ?>
                <label for="return-message" class="form-label">Написать покупателю (не дозвонились — сообщение на почту, заявка остаётся «На рассмотрении»)</label>
                <textarea id="return-message" name="message" class="form-control" rows="3" maxlength="<?= $commentMax ?>" required></textarea>
                <button type="submit" class="btn btn-outline-primary mt-2">Отправить сообщение</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
