<?php

declare(strict_types=1);

/**
 * «Запись создана» — /booking/success/{id} (phase-4.md, Таск 4).
 * Доступ проверен в BookingController::canView() — сюда попадают только
 * сессия, создавшая Запись, либо её владелец. Оформление — на языке макета
 * Patte (кремовая карточка с бейджем, фиолетовая сводка визита), стили —
 * блок `.booking-done` в petpark.css.
 *
 * @var array<string, mixed> $booking Models/Booking.php bookingFindById()
 */

$pageTitle = seoTitle('booking-success');
$pageDescription = seoDescription('booking-success');
$robotsNoindex = true;
$footerVariant = 'catalog';

$bannerTitle = 'Запись создана';
$breadcrumbs = [
    ['name' => 'Главная', 'url' => '/'],
    ['name' => 'Запись на услуги', 'url' => '/booking'],
    ['name' => 'Запись создана', 'url' => null],
];

$needsDeposit = $booking['status'] === 'slot_selected';
$scheduledAt = new DateTimeImmutable((string) $booking['scheduled_at']);

$monthNames = [
    1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля', 5 => 'мая', 6 => 'июня',
    7 => 'июля', 8 => 'августа', 9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
];
$weekdayNames = [
    1 => 'понедельник', 2 => 'вторник', 3 => 'среда', 4 => 'четверг',
    5 => 'пятница', 6 => 'суббота', 7 => 'воскресенье',
];
$visitDate = sprintf(
    '%d %s %s, %s',
    (int) $scheduledAt->format('j'),
    $monthNames[(int) $scheduledAt->format('n')],
    $scheduledAt->format('Y'),
    $weekdayNames[(int) $scheduledAt->format('N')]
);
$visitTime = $scheduledAt->format('H:i');

$holdUntil = $booking['slot_hold_expires_at'] !== null
    ? (new DateTimeImmutable((string) $booking['slot_hold_expires_at']))->format('H:i')
    : null;

$totalKopecks = 0;
$totalMinutes = 0;
foreach ($booking['services'] as $service) {
    $totalKopecks += orderMoneyToKopecks((string) $service['price']);
    $totalMinutes += (int) $service['duration_minutes'];
}

ob_start();
include __DIR__ . '/components/page-banner.php';
?>
<section class="gap booking-done">
    <div class="container">
        <div class="row">
            <div class="col-lg-7">
                <div class="booking-done__card">
                    <div class="booking-done__badge">
                        <i class="fa-solid <?= $needsDeposit ? 'fa-clock' : 'fa-check' ?> booking-done__badge-icon" aria-hidden="true"></i>
                        <svg class="booking-done__badge-ring" width="138" height="138" viewBox="0 0 673 673" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M9.82698 416.603C-19.0352 298.701 18.5108 173.372 107.497 90.7633L110.607 96.5197C24.3117 177.199 -12.311 298.935 15.0502 413.781L9.82698 416.603ZM89.893 565.433C172.674 654.828 298.511 692.463 416.766 663.224L414.077 658.245C298.613 686.363 175.954 649.666 94.9055 562.725L89.893 565.433ZM656.842 259.141C685.039 374.21 648.825 496.492 562.625 577.656L565.413 582.817C654.501 499.935 691.9 374.187 662.536 256.065L656.842 259.141ZM581.945 107.518C499.236 18.8371 373.997 -18.4724 256.228 10.5134L259.436 16.4515C373.888 -10.991 495.248 25.1518 576.04 110.708L581.945 107.518Z" fill="#000"></path>
                        </svg>
                    </div>

                    <h2 class="booking-done__title">
                        <?= $needsDeposit ? 'Время удержано за вами' : 'Вы записаны!' ?>
                    </h2>
                    <p class="booking-done__lead">
                        <?php if ($needsDeposit): ?>
                            Осталось внести депозит — и запись подтвердится автоматически.
                        <?php else: ?>
                            Запись №<?= (int) $booking['id'] ?> подтверждена. Ждём вас и вашего питомца
                            <?= e($visitDate) ?> в <?= e($visitTime) ?>.
                        <?php endif; ?>
                    </p>

                    <?php if ($needsDeposit): ?>
                        <div class="booking-done__notice" role="status">
                            <i class="fa-solid fa-hourglass-half booking-done__notice-icon" aria-hidden="true"></i>
                            <p>
                                Слот закреплён за вами на <?= (int) BOOKING_SLOT_HOLD_MINUTES ?> минут<?= $holdUntil !== null ? ' — до ' . e($holdUntil) : '' ?>.
                                Депозит <strong><?= e(cartFormatMoney((string) $booking['deposit_amount'])) ?> ₽</strong>
                                вернём полностью при отмене не позже чем за <?= (int) BOOKING_CANCEL_THRESHOLD_HOURS ?> часа до визита.
                                Оплата станет доступна на следующем шаге.
                            </p>
                        </div>
                    <?php endif; ?>

                    <h3 class="booking-done__subtitle">Что вас ждёт</h3>
                    <ul class="booking-done__services">
                        <?php foreach ($booking['services'] as $service): ?>
                            <li class="booking-done__service">
                                <i class="fa-solid fa-check booking-done__service-icon" aria-hidden="true"></i>
                                <span class="booking-done__service-name"><?= e((string) $service['service_name']) ?></span>
                                <span class="booking-done__service-meta">
                                    <?= (int) $service['duration_minutes'] ?> мин · <?= e(cartFormatMoney((string) $service['price'])) ?> ₽
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="booking-done__actions">
                        <a class="button" href="/">На главную</a>
                        <a class="booking-done__link" href="/catalog">Заглянуть в каталог</a>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <aside class="booking-done__visit" aria-labelledby="booking-done-visit-title">
                    <h3 class="booking-done__visit-title" id="booking-done-visit-title">Ваш визит</h3>
                    <p class="booking-done__visit-time"><?= e($visitTime) ?></p>
                    <p class="booking-done__visit-date"><?= e($visitDate) ?></p>
                    <dl class="booking-done__visit-list">
                        <dt>Специалист</dt>
                        <dd><?= e((string) $booking['specialist_name']) ?></dd>
                        <dt>Питомец</dt>
                        <dd><?= e((string) $booking['pet_name']) ?></dd>
                        <dt>Длительность</dt>
                        <dd><?= $totalMinutes ?> мин</dd>
                        <dt>Стоимость</dt>
                        <dd><?= e(cartFormatMoney(orderKopecksToMoney($totalKopecks))) ?> ₽</dd>
                        <dt>Номер записи</dt>
                        <dd>№<?= (int) $booking['id'] ?></dd>
                    </dl>
                    <p class="booking-done__visit-hint">
                        Город: <?= e(SHOP_CITY) ?>. Если планы изменятся — отмените запись заранее, мы освободим время для других.
                    </p>
                </aside>
            </div>
        </div>
    </div>
</section>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/layouts/public.php';
