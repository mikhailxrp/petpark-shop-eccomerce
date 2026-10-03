<?php

declare(strict_types=1);

/**
 * Профиль Специалиста — /team/{slug} (phase-8.md, Таск 6), макет
 * `team-details.html`. Телефон, email и график не выводятся. Блок биографии
 * скрыт при пустой `bio`; блок Услуг — при их отсутствии.
 * @var array{name: string, slug: string, position: string, bio: ?string, photo: string, services: list<array{name: string, slug: string}>} $specialist
 */

$breadcrumbs = [
    ['name' => 'Главная', 'url' => '/'],
    ['name' => 'О компании', 'url' => '/about#team'],
    ['name' => $specialist['name'], 'url' => null],
];
$bannerTitle = $specialist['name'];

ob_start();
?>
<?php include __DIR__ . '/components/page-banner.php'; ?>

<section class="gap no-bottom">
    <div class="container">
        <div class="looking team-video position-relative">
            <div class="team-details">
                <h6><?= e($specialist['position']) ?></h6>
                <h2 class="team-details__name"><?= e($specialist['name']) ?></h2>
                <?php if ($specialist['bio'] !== null && trim($specialist['bio']) !== ''): ?>
                    <p><?= nl2br(e($specialist['bio'])) ?></p>
                <?php endif; ?>
                <a href="/booking" class="button team-details__button">Записаться</a>
            </div>
            <div class="video">
                <?php $ringSize = 480; $ringFill = '#000'; include __DIR__ . '/components/ring-svg.php'; ?>
                <img src="<?= e($specialist['photo']) ?>" alt="<?= e($specialist['name'] . ', ' . $specialist['position']) ?>" width="437" height="437">
            </div>
        </div>
    </div>
</section>

<?php if ($specialist['services'] !== []): ?>
<section class="gap no-bottom">
    <div class="container">
        <h2 class="team-details__services-title">Услуги специалиста</h2>
        <ul class="team-details__services">
            <?php foreach ($specialist['services'] as $service): ?>
                <li><a href="/services/<?= e($service['slug']) ?>"><?= e($service['name']) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<section class="gap page-back">
    <div class="container">
        <p><a href="/about#team">← Вся команда</a></p>
    </div>
</section>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/layouts/public.php';
