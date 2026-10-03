<?php

declare(strict_types=1);

/**
 * Мои заказы — /account/orders (`FR-ACC-001`, phase-7.md, Таск 1).
 * @var list<array<string, mixed>> $orders     ordersPageByUser()
 * @var int                        $page       Текущая страница (с 1)
 * @var int                        $totalPages
 */

$pageTitle = seoTitle('account-orders');
$pageDescription = seoDescription('account-orders');
$robotsNoindex = true;
$footerVariant = 'catalog';

$bannerTitle = 'Мои заказы';
$breadcrumbs = [
    ['name' => 'Главная', 'url' => '/'],
    ['name' => 'Личный кабинет', 'url' => '/account'],
    ['name' => 'Мои заказы', 'url' => null],
];

$orderStatusLabels = [
    'new'              => 'Новый',
    'confirmed'        => 'Подтверждён',
    'assembled'        => 'Собран',
    'shipped'          => 'Отгружен',
    'ready_for_pickup' => 'Готов к выдаче',
    'delivered'        => 'Доставлен',
    'picked_up'        => 'Выдан',
    'cancelled'        => 'Отменён',
];

$pageUrl = static fn (int $target): string => $target > 1 ? '/account/orders?page=' . $target : '/account/orders';

ob_start();
include __DIR__ . '/../components/page-banner.php';
?>
<section class="gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-3">
                <?php $accountActive = 'orders'; include __DIR__ . '/../components/account-nav.php'; ?>
            </div>
            <div class="col-lg-9">
                <h2 class="account-heading">История заказов</h2>
                <?php if ($orders === []): ?>
                    <p>У вас пока нет заказов. <a href="/catalog">Перейти в каталог</a></p>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <?php $orderedAt = new DateTimeImmutable((string) $order['created_at']); ?>
                        <article class="account-card order-card">
                            <h3 class="account-card__title">
                                Заказ №<?= (int) $order['id'] ?>
                                <span class="order-status order-status--<?= e((string) $order['status']) ?>">
                                    <?= e($orderStatusLabels[$order['status']] ?? (string) $order['status']) ?>
                                </span>
                            </h3>
                            <dl class="pet-card__details">
                                <dt>Дата</dt>
                                <dd><?= e($orderedAt->format('d.m.Y H:i')) ?></dd>
                                <dt>Сумма</dt>
                                <dd><?= e(cartFormatMoney((string) $order['total'])) ?> ₽</dd>
                            </dl>
                            <div class="pet-card__actions">
                                <a class="button" href="/account/orders/<?= (int) $order['id'] ?>">Подробнее</a>
                            </div>
                        </article>
                    <?php endforeach; ?>

                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Страницы списка заказов" class="d-flex justify-content-center">
                            <ul class="pagination m-auto">
                                <li class="prev<?= $page <= 1 ? ' disabled' : '' ?>">
                                    <?php if ($page > 1): ?>
                                        <a href="<?= e($pageUrl($page - 1)) ?>" aria-label="Предыдущая страница"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
                                    <?php else: ?>
                                        <span><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></span>
                                    <?php endif; ?>
                                </li>
                                <?php for ($number = 1; $number <= $totalPages; $number++): ?>
                                    <li class="<?= $number === $page ? 'active' : '' ?>">
                                        <a href="<?= e($pageUrl($number)) ?>"<?= $number === $page ? ' aria-current="page"' : '' ?>><?= $number ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="next<?= $page >= $totalPages ? ' disabled' : '' ?>">
                                    <?php if ($page < $totalPages): ?>
                                        <a href="<?= e($pageUrl($page + 1)) ?>" aria-label="Следующая страница"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                                    <?php else: ?>
                                        <span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                                    <?php endif; ?>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/public.php';
