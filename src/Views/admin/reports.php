<?php

declare(strict_types=1);

/**
 * Отчёты — /admin/reports (phase-7.md, Таск 10; FR-ADM-004). Только Владелец.
 * Цифры и таблицы — в HTML без JS; графики (Chart.js) читают JSON-блок ниже.
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var string $period day|week|month
 * @var array<string, string> $periods Код периода → подпись
 * @var string $date Опорная дата, Y-m-d
 * @var string $periodLabel
 * @var array{days: list<array{day: string, orders_count: int, revenue: string}>, orders_count: int, revenue: string} $orders
 * @var list<array{source: string, label: string, orders_count: int, revenue: string}> $sources
 * @var array<int, array{product_name: string, quantity: int|string, revenue: string}> $topProducts
 * @var array{kinds: list<array{kind: string, label: string, bookings_count: int, services_count: int, revenue: string}>, bookings_count: int, services_count: int, revenue: string} $services
 */

$money = static fn (string $amount): string => number_format((float) $amount, 2, ',', ' ') . ' ₽';

$chartData = [
    'days'     => array_map(static fn (array $row): string => date('d.m', (int) strtotime($row['day'])), $orders['days']),
    'revenue'  => array_map(static fn (array $row): float => (float) $row['revenue'], $orders['days']),
    'services' => [
        'labels'  => array_column($services['kinds'], 'label'),
        'revenue' => array_map(static fn (array $row): float => (float) $row['revenue'], $services['kinds']),
    ],
];

ob_start();
?>
<div class="my-4">
    <h1 class="mb-0">Отчёты</h1>
    <p class="mb-0 text-muted">Период: <?= e($periodLabel) ?>. Отменённые Заказы не учитываются.</p>
</div>

<div class="card">
    <div class="card-body">
        <form method="get" action="/admin/reports" class="row g-2 align-items-end">
            <div class="col-12 col-md-auto">
                <label for="report-period" class="form-label">Период</label>
                <select id="report-period" name="period" class="form-select">
                    <?php foreach ($periods as $code => $label): ?>
                        <option value="<?= e($code) ?>"<?= $code === $period ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-auto">
                <label for="report-date" class="form-label">Дата внутри периода</label>
                <input type="date" id="report-date" name="date" class="form-control" value="<?= e($date) ?>" required>
            </div>
            <div class="col-12 col-md-auto">
                <button type="submit" class="btn btn-primary">Показать</button>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-12 col-md-6">
        <div class="card">
            <div class="card-body">
                <p class="text-muted mb-1">Выручка по Заказам</p>
                <p class="fs-4 fw-semibold mb-0"><?= e($money($orders['revenue'])) ?></p>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card">
            <div class="card-body">
                <p class="text-muted mb-1">Число Заказов</p>
                <p class="fs-4 fw-semibold mb-0"><?= $orders['orders_count'] ?></p>
            </div>
        </div>
    </div>
</div>

<section class="card" aria-labelledby="report-sources-title">
    <div class="card-header">
        <h2 class="card-title" id="report-sources-title">Выручка по источникам</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Источник</th>
                        <th scope="col">Заказов</th>
                        <th scope="col">Выручка</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sources as $row): ?>
                        <tr>
                            <th scope="row"><?= e($row['label']) ?></th>
                            <td><?= $row['orders_count'] ?></td>
                            <td><?= e($money($row['revenue'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="card" aria-labelledby="report-revenue-title">
    <div class="card-header">
        <h2 class="card-title" id="report-revenue-title">Выручка по дням</h2>
    </div>
    <div class="card-body">
        <?php if ($orders['orders_count'] === 0): ?>
            <p class="mb-0 text-muted">За выбранный период Заказов нет.</p>
        <?php else: ?>
            <div class="report-chart"><canvas id="report-revenue-chart" role="img" aria-label="График выручки по дням"></canvas></div>
            <div class="table-responsive mt-3">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">День</th>
                            <th scope="col">Заказов</th>
                            <th scope="col">Выручка</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders['days'] as $row): ?>
                            <tr>
                                <th scope="row"><?= e(date('d.m.Y', (int) strtotime($row['day']))) ?></th>
                                <td><?= $row['orders_count'] ?></td>
                                <td><?= e($money($row['revenue'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="card" aria-labelledby="report-top-title">
    <div class="card-header">
        <h2 class="card-title" id="report-top-title">Самые продаваемые Товары</h2>
    </div>
    <div class="card-body">
        <?php if ($topProducts === []): ?>
            <p class="mb-0 text-muted">За выбранный период продаж нет.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Товар</th>
                            <th scope="col">Продано, шт.</th>
                            <th scope="col">Сумма</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topProducts as $row): ?>
                            <tr>
                                <th scope="row"><?= e((string) $row['product_name']) ?></th>
                                <td><?= (int) $row['quantity'] ?></td>
                                <td><?= e($money((string) $row['revenue'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="card" aria-labelledby="report-services-title">
    <div class="card-header">
        <h2 class="card-title" id="report-services-title">Услуги (завершённые Записи)</h2>
    </div>
    <div class="card-body">
        <p class="text-muted">Период — по дате визита, сумма — по ценам Услуг в Записи, без Депозитов.</p>
        <?php if ($services['bookings_count'] === 0): ?>
            <p class="text-muted">За выбранный период завершённых Записей нет.</p>
        <?php else: ?>
            <div class="report-chart"><canvas id="report-services-chart" role="img" aria-label="График выручки по видам Услуг"></canvas></div>
        <?php endif; ?>
        <div class="table-responsive mt-3">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Вид</th>
                        <th scope="col">Записей</th>
                        <th scope="col">Услуг</th>
                        <th scope="col">Сумма</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services['kinds'] as $row): ?>
                        <tr>
                            <th scope="row"><?= e($row['label']) ?></th>
                            <td><?= $row['bookings_count'] ?></td>
                            <td><?= $row['services_count'] ?></td>
                            <td><?= e($money($row['revenue'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row">Итого</th>
                        <td><?= $services['bookings_count'] ?></td>
                        <td><?= $services['services_count'] ?></td>
                        <td><?= e($money($services['revenue'])) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</section>

<script type="application/json" id="report-data"><?= json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script src="/admin/assets/libs/chart.js/chart.min.js"></script>
<script type="module" src="/admin/js/reports.js"></script>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
