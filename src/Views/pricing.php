<?php

declare(strict_types=1);

/**
 * Тарифы на услуги — /pricing (phase-8.md, Таск 5), макет `pricing-packages.html`.
 * Разовая цена каждой Услуги из `services.price` (Q-045: без периода подписки);
 * Депозит — только у груминга. Списки-«преимущества» макета не выводятся — данных о них нет.
 * @var array<int, array<string, mixed>> $groomingServices
 * @var array<int, array<string, mixed>> $vetServices
 */

$breadcrumbs = [
    ['name' => 'Главная', 'url' => '/'],
    ['name' => 'Тарифы', 'url' => null],
];
$bannerTitle = 'Тарифы на услуги';

$sections = [
    ['id' => 'grooming', 'title' => 'Груминг', 'services' => $groomingServices,
        'icon' => '/assets/img/package-1.png', 'photo' => '/assets/img/home-page/package-1.jpg',
        'photoAlt' => 'Собака с мыльной пеной на голове во время мытья'],
    ['id' => 'vet', 'title' => 'Ветеринария', 'services' => $vetServices,
        'icon' => '/assets/img/package-2.png', 'photo' => '/assets/img/home-page/package-2.jpg',
        'photoAlt' => 'Девушка играет с коричневым пуделем на диване'],
];

ob_start();
?>
<?php include __DIR__ . '/components/page-banner.php'; ?>

<section class="gap">
    <div class="container">
        <?php foreach ($sections as $section): ?>
            <section id="<?= e($section['id']) ?>" class="pricing-section">
                <h2 class="pricing-section__title"><?= e($section['title']) ?></h2>
                <?php if ($section['services'] === []): ?>
                    <p>Услуги этого раздела пока недоступны.</p>
                <?php endif; ?>
                <?php foreach ($section['services'] as $service): ?>
                    <div class="package two">
                        <div class="package-text">
                            <i><img src="<?= e($section['icon']) ?>" alt=""></i>
                            <div>
                                <h4><?= e(cartFormatMoney((string) $service['price'])) ?> ₽</h4>
                                <h3><?= e((string) $service['name']) ?></h3>
                                <p class="pricing-section__meta"><?= (int) $service['duration_minutes'] ?> мин</p>
                                <?php if ((string) $service['kind'] === 'grooming' && $service['deposit_amount'] !== null): ?>
                                    <p class="pricing-section__meta">Депозит при записи: <?= e(cartFormatMoney((string) $service['deposit_amount'])) ?> ₽</p>
                                <?php endif; ?>
                                <a href="/booking" class="button">Записаться</a>
                            </div>
                        </div>
                        <figure>
                            <img src="<?= e($section['photo']) ?>" alt="<?= e($section['photoAlt']) ?>">
                        </figure>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </div>
</section>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/layouts/public.php';
