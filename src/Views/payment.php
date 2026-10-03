<?php

declare(strict_types=1);

/**
 * «Оплата» — /payment (phase-9.md, Таск 5). Вступление — `content_pages.body`
 * (уже очищено contentHtmlSanitize()); время резерва — из ORDER_RESERVE_MINUTES.
 * @var array<string, mixed> $page
 * @var string               $bodyHtml       очищенный `body`
 * @var int                  $reserveMinutes время резерва заказа, минут
 */

$breadcrumbs = [
    ['name' => 'Главная', 'url' => '/'],
    ['name' => (string) $page['title'], 'url' => null],
];
$bannerTitle = (string) $page['title'];

ob_start();
?>
<?php include __DIR__ . '/components/page-banner.php'; ?>

<section class="gap">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="info-page__intro"><?= $bodyHtml ?></div>
                <div class="row">
                    <?php
                    $infoCard = [
                        'icon' => 'fa-credit-card',
                        'title' => 'Картой на сайте',
                        'badge' => 'Онлайн',
                        'text' => 'Оплатите заказ сразу при оформлении — быстро и без наличных.',
                        'items' => [],
                    ];
                    include __DIR__ . '/components/info-card.php';

                    $infoCard = [
                        'icon' => 'fa-money-bill-wave',
                        'title' => 'При получении',
                        'badge' => 'Наличными или картой',
                        'text' => 'Заплатите в магазине при самовывозе или курьеру при доставке.',
                        'items' => [],
                    ];
                    include __DIR__ . '/components/info-card.php';

                    $infoBanner = [
                        'icon' => 'fa-clock',
                        'title' => 'Резерв товара — ' . $reserveMinutes . ' минут',
                        'text' => 'Если за это время заказ не будет оплачен или подтверждён магазином, резерв снимается, а заказ отменяется.',
                    ];
                    include __DIR__ . '/components/info-banner.php';
                    ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/layouts/public.php';
