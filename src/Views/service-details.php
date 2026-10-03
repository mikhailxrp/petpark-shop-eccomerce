<?php

declare(strict_types=1);

/**
 * Детали Услуги — /services/{slug} (phase-8.md, Таск 4), макет
 * `service-details.html`. Название, описание, цена, длительность и Депозит —
 * из строки `services`; Депозит показан только если он задан (груминг).
 * Тексты-списки макета («Graceful goldfish…») не выводятся — данных о них нет.
 * @var array<string, mixed> $service serviceFindActiveBySlug()
 */

$breadcrumbs = [
    ['name' => 'Главная', 'url' => '/'],
    ['name' => 'Услуги', 'url' => '/services'],
    ['name' => (string) $service['name'], 'url' => null],
];
$bannerTitle = (string) $service['name'];

$isVet = (string) $service['kind'] === 'vet';
$icon = $isVet ? '/assets/img/welcome-to-4.png' : '/assets/img/welcome-to-1.png';
$photo = $isVet ? '/assets/img/home-page/package-2.jpg' : '/assets/img/home-page/package-1.jpg';
$photoAlt = $isVet
    ? 'Девушка играет с коричневым пуделем на диване'
    : 'Собака с мыльной пеной на голове во время мытья';

ob_start();
?>
<?php include __DIR__ . '/components/page-banner.php'; ?>

<section class="gap no-bottom service-details">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="pet-grooming">
                    <i><img src="<?= e($icon) ?>" alt=""></i>
                    <?php $ringSize = 138; $ringFill = '#000'; include __DIR__ . '/components/ring-svg.php'; ?>
                    <h2 class="service-details__title"><?= e((string) $service['name']) ?></h2>
                    <p><?= e((string) $service['description']) ?></p>
                    <dl class="service-details__facts">
                        <dt>Длительность</dt>
                        <dd><?= (int) $service['duration_minutes'] ?> мин</dd>
                        <dt>Стоимость</dt>
                        <dd><?= e(cartFormatMoney((string) $service['price'])) ?> ₽</dd>
                        <?php if ($service['deposit_amount'] !== null): ?>
                            <dt>Депозит при записи</dt>
                            <dd><?= e(cartFormatMoney((string) $service['deposit_amount'])) ?> ₽</dd>
                        <?php endif; ?>
                    </dl>
                    <a href="/booking" class="button">Записаться</a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="dog-walker two d-block">
                    <img src="<?= e($photo) ?>" alt="<?= e($photoAlt) ?>">
                    <img src="/assets/img/dabal-foot.png" class="dabal-foot" alt="">
                </div>
            </div>
        </div>
    </div>
</section>

<section class="gap page-back">
    <div class="container">
        <p><a href="/services">← Все услуги</a></p>
    </div>
</section>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/layouts/public.php';
