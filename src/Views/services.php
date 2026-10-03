<?php

declare(strict_types=1);

/**
 * Список Услуг — /services (phase-8.md, Таск 4), макет `services.html`.
 * Карточки — из `services` (servicesPublicList()); цена и длительность
 * выводятся из тех же строк, что в форме Записи. Блоки макета про
 * видео, FAQ-членство и логотипы партнёров не выводятся — данных о них нет.
 * @var array<int, array<string, mixed>> $services
 */

$breadcrumbs = [
    ['name' => 'Главная', 'url' => '/'],
    ['name' => 'Услуги', 'url' => null],
];
$bannerTitle = 'Услуги';

$kindIcons = [
    'grooming' => '/assets/img/welcome-to-1.png',
    'vet' => '/assets/img/welcome-to-4.png',
];

ob_start();
?>
<?php include __DIR__ . '/components/page-banner.php'; ?>

<section class="gap services">
    <div class="container">
        <?php if ($services === []): ?>
            <p>Услуги пока недоступны.</p>
        <?php else: ?>
            <div class="row">
                <?php foreach ($services as $service): ?>
                    <?php $icon = $kindIcons[(string) $service['kind']] ?? $kindIcons['grooming']; ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="pet-grooming service-card">
                            <i><img src="<?= e($icon) ?>" alt=""></i>
                            <?php $ringSize = 138; $ringFill = '#000'; include __DIR__ . '/components/ring-svg.php'; ?>
                            <a href="/services/<?= e((string) $service['slug']) ?>"><h2 class="service-card__title"><?= e((string) $service['name']) ?></h2></a>
                            <p><?= e((string) $service['description']) ?></p>
                            <p class="service-card__meta">
                                <?= (int) $service['duration_minutes'] ?> мин ·
                                <?= e(cartFormatMoney((string) $service['price'])) ?> ₽
                            </p>
                            <a href="/services/<?= e((string) $service['slug']) ?>" class="button service-card__link">Подробнее</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="gap">
    <div class="container">
        <div class="mockup">
            <h3>Запишите питомца на <span>груминг</span> или ветконсультацию</h3>
            <div class="mockup-img">
                <img src="/assets/img/home-page/mockup.png" alt="Собака смотрит вверх, ожидая лакомство">
            </div>
            <div class="mockup-text">
                <p>Выберите услугу, специалиста и удобное время — запись занимает пару минут.</p>
                <a href="/booking" class="button">Записаться</a>
            </div>
        </div>
    </div>
</section>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/layouts/public.php';
