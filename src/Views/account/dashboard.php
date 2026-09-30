<?php

declare(strict_types=1);

/**
 * Личный кабинет Покупателя — заглушка-приёмник после входа
 * (phase-1.md: содержимое — Фаза 7).
 */

$pageTitle = seoTitle('generic');
$pageDescription = seoDescription('generic');
$footerVariant = 'catalog';

$bannerTitle = 'Личный кабинет';
$breadcrumbs = [
    ['name' => 'Главная', 'url' => '/'],
    ['name' => 'Личный кабинет', 'url' => null],
];

ob_start();
include __DIR__ . '/../components/page-banner.php';
?>
<section class="gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-3">
                <?php $accountActive = 'overview'; include __DIR__ . '/../components/account-nav.php'; ?>
            </div>
            <div class="col-lg-9">
                <article class="account-card">
                    <h2 class="account-heading">Добро пожаловать в личный кабинет</h2>
                    <p>Здесь вы управляете карточками питомцев — они понадобятся для записи на услуги. Заказы, записи и избранное появятся позже.</p>
                </article>
            </div>
        </div>
    </div>
</section>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/public.php';
