<?php

declare(strict_types=1);

/**
 * Мои записи — /account/bookings (`FR-SV-008`, phase-4.md, Таск 6).
 * Ближайшие подтверждённые Записи Покупателя; отмена — не позже чем за
 * `$thresholdHours` часов до визита, дальше — через администратора.
 * @var list<array<string, mixed>> $bookings       bookingsUpcomingByUser() + флаг can_cancel
 * @var int                        $thresholdHours BOOKING_CANCEL_THRESHOLD_HOURS
 * @var string|null                $success        getFlash('success')
 * @var string|null                $error          getFlash('error')
 */

$pageTitle = seoTitle('account-bookings');
$pageDescription = seoDescription('account-bookings');
$robotsNoindex = true;
$footerVariant = 'catalog';

$bannerTitle = 'Мои записи';
$breadcrumbs = [
    ['name' => 'Главная', 'url' => '/'],
    ['name' => 'Личный кабинет', 'url' => '/account'],
    ['name' => 'Мои записи', 'url' => null],
];

ob_start();
include __DIR__ . '/../components/page-banner.php';
?>
<section class="gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-3">
                <?php $accountActive = 'bookings'; include __DIR__ . '/../components/account-nav.php'; ?>
            </div>
            <div class="col-lg-9">
                <?php if ($success !== null): ?>
                    <div class="alert alert-success" role="status"><?= e($success) ?></div>
                <?php endif; ?>
                <?php if ($error !== null): ?>
                    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
                <?php endif; ?>

                <h2 class="account-heading">Ближайшие записи</h2>
                <?php if ($bookings === []): ?>
                    <p>У вас нет предстоящих записей. <a href="/booking">Записаться на услугу</a></p>
                <?php else: ?>
                    <?php foreach ($bookings as $booking): ?>
                        <?php $visitAt = new DateTimeImmutable((string) $booking['scheduled_at']); ?>
                        <article class="account-card pet-card">
                            <h3 class="account-card__title">
                                <?= e($visitAt->format('d.m.Y')) ?> в <?= e($visitAt->format('H:i')) ?>
                            </h3>
                            <dl class="pet-card__details">
                                <dt>Услуги</dt>
                                <dd><?= e((string) $booking['service_names']) ?></dd>
                                <dt>Питомец</dt>
                                <dd><?= e((string) $booking['pet_name']) ?></dd>
                                <dt>Специалист</dt>
                                <dd><?= e((string) $booking['specialist_name']) ?></dd>
                                <?php if ($booking['deposit_status'] === 'held'): ?>
                                    <dt>Депозит</dt>
                                    <dd><?= e(cartFormatMoney((string) $booking['deposit_amount'])) ?> ₽ — вернём при отмене</dd>
                                <?php endif; ?>
                            </dl>
                            <?php if ($booking['can_cancel']): ?>
                                <div class="pet-card__actions">
                                    <form method="post" action="/account/bookings/<?= (int) $booking['id'] ?>/cancel">
                                        <?= csrfField() ?>
                                        <button type="submit" class="pet-card__delete">Отменить запись</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <p class="account-card__hint">
                                    До визита меньше <?= (int) $thresholdHours ?> ч — отменить онлайн нельзя,
                                    обратитесь к администратору.
                                </p>
                            <?php endif; ?>
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
