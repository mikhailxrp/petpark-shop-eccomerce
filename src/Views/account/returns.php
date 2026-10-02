<?php

declare(strict_types=1);

/**
 * Возвраты — /account/returns (`FR-RET-001`, phase-6.md, Таск 5).
 * Заказы, доступные для Возврата, и свои заявки со статусами. Полная история
 * Заказов (`FR-ACC-001`) — Фаза 7, замещает этот экран.
 * @var list<array<string, mixed>> $orders  Заказы, на которые можно подать заявку
 * @var list<array<string, mixed>> $returns returnsByUser()
 * @var string|null                $success getFlash('success')
 * @var string|null                $error   getFlash('error')
 */

$pageTitle = seoTitle('account-returns');
$pageDescription = seoDescription('account-returns');
$robotsNoindex = true;
$footerVariant = 'catalog';

$bannerTitle = 'Возвраты';
$breadcrumbs = [
    ['name' => 'Главная', 'url' => '/'],
    ['name' => 'Личный кабинет', 'url' => '/account'],
    ['name' => 'Возвраты', 'url' => null],
];

$returnStatusLabels = [
    'submitted' => 'Подана',
    'in_review' => 'На рассмотрении',
    'approved'  => 'Одобрена',
    'rejected'  => 'Отклонена',
    'completed' => 'Завершена',
];

ob_start();
include __DIR__ . '/../components/page-banner.php';
?>
<section class="gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-3">
                <?php $accountActive = 'returns'; include __DIR__ . '/../components/account-nav.php'; ?>
            </div>
            <div class="col-lg-9">
                <?php if ($success !== null): ?>
                    <div class="alert alert-success" role="status"><?= e($success) ?></div>
                <?php endif; ?>
                <?php if ($error !== null): ?>
                    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
                <?php endif; ?>

                <h2 class="account-heading">Заказы, доступные для возврата</h2>
                <?php if ($orders === []): ?>
                    <p>Сейчас нет заказов, на которые можно подать заявку на возврат. Вернуть можно полученный заказ.</p>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <?php $orderedAt = new DateTimeImmutable((string) $order['created_at']); ?>
                        <article class="account-card return-card">
                            <h3 class="account-card__title">Заказ №<?= (int) $order['id'] ?></h3>
                            <dl class="pet-card__details">
                                <dt>Дата</dt>
                                <dd><?= e($orderedAt->format('d.m.Y')) ?></dd>
                                <dt>Сумма</dt>
                                <dd><?= e(cartFormatMoney((string) $order['total'])) ?> ₽</dd>
                            </dl>
                            <div class="pet-card__actions">
                                <a class="button" href="/account/returns/<?= (int) $order['id'] ?>/new">Оформить возврат</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>

                <h2 class="account-heading">Мои заявки</h2>
                <?php if ($returns === []): ?>
                    <p>Вы ещё не подавали заявок на возврат.</p>
                <?php else: ?>
                    <?php foreach ($returns as $return): ?>
                        <?php
                        $status = (string) $return['status'];
                        $submittedAt = new DateTimeImmutable((string) $return['created_at']);
                        ?>
                        <article class="account-card return-card">
                            <h3 class="account-card__title">
                                Заказ №<?= (int) $return['order_id'] ?>
                                <span class="return-status return-status--<?= e($status) ?>"><?= e($returnStatusLabels[$status] ?? $status) ?></span>
                            </h3>
                            <dl class="pet-card__details">
                                <dt>Подана</dt>
                                <dd><?= e($submittedAt->format('d.m.Y H:i')) ?></dd>
                                <dt>Причина</dt>
                                <dd><?= nl2br(e((string) $return['reason'])) ?></dd>
                                <dt>Фото</dt>
                                <dd><?= (int) $return['photo_count'] ?></dd>
                                <?php if ($return['decision_comment'] !== null && $return['decision_comment'] !== ''): ?>
                                    <dt>Ответ магазина</dt>
                                    <dd><?= nl2br(e((string) $return['decision_comment'])) ?></dd>
                                <?php endif; ?>
                            </dl>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/public.php';
