<?php

declare(strict_types=1);

/**
 * «Доставка» — /delivery (phase-9.md, Таск 5). Вступление — `content_pages.body`
 * (уже очищено contentHtmlSanitize()); цифры — из констант чекаута, не из `body`.
 * @var array<string, mixed> $page
 * @var string               $bodyHtml      очищенный `body`
 * @var string               $freeThreshold порог бесплатной доставки, ₽
 * @var string               $courierCost   стоимость курьера ниже порога, ₽
 * @var string               $pickupAddress адрес магазина
 * @var string               $pickupHours   часы работы
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
                        'icon' => 'fa-store',
                        'title' => 'Самовывоз',
                        'badge' => 'Бесплатно',
                        'text' => 'Заберите заказ в магазине в удобное время.',
                        'items' => ['Адрес: ' . $pickupAddress, 'Часы работы: ' . $pickupHours],
                    ];
                    include __DIR__ . '/components/info-card.php';

                    $infoCard = [
                        'icon' => 'fa-truck-fast',
                        'title' => 'Курьером по Ростову-на-Дону',
                        'badge' => $courierCost . ' ₽',
                        'text' => 'Привезём заказ до двери. Улицу и дом укажите при оформлении заказа.',
                        'items' => ['Стоимость доставки: ' . $courierCost . ' ₽', 'Бесплатно при заказе от ' . $freeThreshold . ' ₽'],
                    ];
                    include __DIR__ . '/components/info-card.php';

                    $infoBanner = [
                        'icon' => 'fa-gift',
                        'title' => 'Бесплатная доставка от ' . $freeThreshold . ' ₽',
                        'text' => 'Соберите корзину на нужную сумму — и курьер приедет бесплатно.',
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
