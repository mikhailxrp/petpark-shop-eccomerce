<?php

declare(strict_types=1);

/**
 * Страница-имитация платёжной формы ЮMoney для Депозита Записи —
 * /booking/{id}/pay (phase-4.md, Таск 5, FR-SV-005, ADR-001). Заглушка даёт
 * две кнопки, каждая шлёт на callback свой результат с подписью
 * (YooMoneyStubGateway::signBooking()). Карточных данных не собираем.
 *
 * @var array<string, mixed> $booking           bookingFindById()
 * @var string               $signaturePaid     подпись результата «оплачено»
 * @var string               $signatureDeclined подпись результата «отклонено»
 */

$pageTitle = seoTitle('booking-payment');
$pageDescription = seoDescription('booking-payment');
$robotsNoindex = true;
$footerVariant = 'catalog';

$bookingId = (int) $booking['id'];
$holdUntil = $booking['slot_hold_expires_at'] !== null
    ? (new DateTimeImmutable((string) $booking['slot_hold_expires_at']))->format('H:i')
    : null;

$bannerTitle = 'Оплата депозита';
$breadcrumbs = [
    ['name' => 'Главная', 'url' => '/'],
    ['name' => 'Запись на услуги', 'url' => '/booking'],
    ['name' => 'Оплата депозита', 'url' => null],
];

ob_start();
include dirname(__DIR__) . '/components/page-banner.php';
?>
<section class="gap checkout-page order-success">
    <div class="container">
        <h2 class="order-success__title">Оплата депозита за запись №<?= $bookingId ?></h2>
        <p>К оплате: <strong><?= e(cartFormatMoney((string) $booking['deposit_amount'])) ?> ₽</strong></p>
        <?php if ($holdUntil !== null): ?>
            <p>Время закреплено за вами до <?= e($holdUntil) ?>.</p>
        <?php endif; ?>
        <p>Это демонстрационная платёжная страница: реальные деньги не списываются.</p>

        <form method="post" action="/booking/<?= $bookingId ?>/pay/callback" class="mt-3">
            <?= csrfField() ?>
            <button type="submit" name="result" value="paid" class="button">Оплата прошла</button>
            <input type="hidden" name="signature" value="<?= e($signaturePaid) ?>">
        </form>

        <form method="post" action="/booking/<?= $bookingId ?>/pay/callback" class="mt-3">
            <?= csrfField() ?>
            <button type="submit" name="result" value="declined" class="button">Оплата отклонена</button>
            <input type="hidden" name="signature" value="<?= e($signatureDeclined) ?>">
        </form>
    </div>
</section>
<?php
$content = (string) ob_get_clean();

require dirname(__DIR__) . '/layouts/public.php';
