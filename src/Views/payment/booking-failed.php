<?php

declare(strict_types=1);

/**
 * Экран «оплата депозита не прошла» — /booking/{id}/pay/failed (phase-4.md,
 * Таск 5). Запись остаётся `slot_selected`, пока держится слот: можно
 * повторить оплату или отказаться от Записи.
 *
 * @var array<string, mixed> $booking bookingFindById()
 * @var string|null          $error   flash 'payment_error'
 */

$pageTitle = seoTitle('booking-payment-failed');
$pageDescription = seoDescription('booking-payment-failed');
$robotsNoindex = true;
$footerVariant = 'catalog';

$bookingId = (int) $booking['id'];

$bannerTitle = 'Оплата не прошла';
$breadcrumbs = [
    ['name' => 'Главная', 'url' => '/'],
    ['name' => 'Запись на услуги', 'url' => '/booking'],
    ['name' => 'Оплата не прошла', 'url' => null],
];

ob_start();
include dirname(__DIR__) . '/components/page-banner.php';
?>
<section class="gap checkout-page order-success">
    <div class="container">
        <h2 class="order-success__title">Оплата депозита за запись №<?= $bookingId ?> не прошла</h2>
        <?php if ($error !== null): ?>
            <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
        <?php endif; ?>
        <p>Время закреплено за вами на ограниченный срок. Повторите оплату или отмените запись — тогда время снова станет доступно другим.</p>

        <a class="button mt-3" href="/booking/<?= $bookingId ?>/pay">Повторить оплату</a>

        <form method="post" action="/booking/<?= $bookingId ?>/release" class="mt-3">
            <?= csrfField() ?>
            <button type="submit" class="button">Отменить запись</button>
        </form>
    </div>
</section>
<?php
$content = (string) ob_get_clean();

require dirname(__DIR__) . '/layouts/public.php';
