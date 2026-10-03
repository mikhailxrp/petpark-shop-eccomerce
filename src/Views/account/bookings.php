<?php

declare(strict_types=1);

/**
 * Мои записи — /account/bookings (`FR-SV-008`, phase-4.md, Таск 6;
 * история по Питомцам — `FR-ACC-002`, phase-7.md, Таск 3). Прошлые и будущие
 * Записи по каждому Питомцу; отмена — не позже чем за `$thresholdHours`
 * часов до визита, дальше — через администратора.
 * @var list<array{pet: array<string, mixed>, bookings: list<array<string, mixed>>}> $groups bookingsGroupByPet(); у Записей флаги is_upcoming, can_cancel
 * @var int                        $page           Текущая страница (с 1)
 * @var int                        $totalPages
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

$pageUrl = static fn (int $target): string => $target > 1 ? '/account/bookings?page=' . $target : '/account/bookings';

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

                <?php if ($groups === []): ?>
                    <p>У вас пока нет записей. <a href="/booking">Записаться на услугу</a></p>
                <?php endif; ?>
                <?php foreach ($groups as $group): ?>
                    <section class="booking-pet" aria-labelledby="booking-pet-<?= (int) $group['pet']['id'] ?>">
                        <h2 class="account-heading" id="booking-pet-<?= (int) $group['pet']['id'] ?>">
                            <?= e((string) $group['pet']['name']) ?>
                        </h2>
                        <?php if ($group['bookings'] === []): ?>
                            <p>У этого питомца пока нет записей. <a href="/booking">Записаться на услугу</a></p>
                        <?php endif; ?>
                        <?php foreach ($group['bookings'] as $booking): ?>
                            <?php
                            $visitAt = new DateTimeImmutable((string) $booking['scheduled_at']);
                            $depositLabel = bookingDepositLabel((string) $booking['deposit_status']);
                            ?>
                            <article class="account-card pet-card">
                                <h3 class="account-card__title">
                                    <?= e($visitAt->format('d.m.Y')) ?> в <?= e($visitAt->format('H:i')) ?>
                                    <span class="booking-status booking-status--<?= e((string) $booking['status']) ?>">
                                        <?= e(bookingStatusLabel((string) $booking['status'])) ?>
                                    </span>
                                </h3>
                                <dl class="pet-card__details">
                                    <dt>Услуги</dt>
                                    <dd><?= e((string) $booking['service_names']) ?></dd>
                                    <dt>Специалист</dt>
                                    <dd><?= e((string) $booking['specialist_name']) ?></dd>
                                    <?php if ($depositLabel !== null): ?>
                                        <dt>Депозит</dt>
                                        <dd><?= e(cartFormatMoney((string) $booking['deposit_amount'])) ?> ₽ — <?= e($depositLabel) ?></dd>
                                    <?php endif; ?>
                                </dl>
                                <?php if ($booking['can_cancel']): ?>
                                    <div class="pet-card__actions">
                                        <form method="post" action="/account/bookings/<?= (int) $booking['id'] ?>/cancel">
                                            <?= csrfField() ?>
                                            <button type="submit" class="pet-card__delete">Отменить запись</button>
                                        </form>
                                    </div>
                                <?php elseif ($booking['is_upcoming']): ?>
                                    <p class="account-card__hint">
                                        До визита меньше <?= (int) $thresholdHours ?> ч — отменить онлайн нельзя,
                                        обратитесь к администратору.
                                    </p>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </section>
                <?php endforeach; ?>

                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Страницы списка записей" class="d-flex justify-content-center">
                        <ul class="pagination m-auto">
                            <li class="prev<?= $page <= 1 ? ' disabled' : '' ?>">
                                <?php if ($page > 1): ?>
                                    <a href="<?= e($pageUrl($page - 1)) ?>" aria-label="Предыдущая страница"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
                                <?php else: ?>
                                    <span><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></span>
                                <?php endif; ?>
                            </li>
                            <?php for ($number = 1; $number <= $totalPages; $number++): ?>
                                <li class="<?= $number === $page ? 'active' : '' ?>">
                                    <a href="<?= e($pageUrl($number)) ?>"<?= $number === $page ? ' aria-current="page"' : '' ?>><?= $number ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="next<?= $page >= $totalPages ? ' disabled' : '' ?>">
                                <?php if ($page < $totalPages): ?>
                                    <a href="<?= e($pageUrl($page + 1)) ?>" aria-label="Следующая страница"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                                <?php else: ?>
                                    <span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                                <?php endif; ?>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/public.php';
