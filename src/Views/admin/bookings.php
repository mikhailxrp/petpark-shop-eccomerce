<?php

declare(strict_types=1);

/**
 * Календарь Записей — /admin/bookings и /specialist (phase-4.md, Таск 7;
 * FR-SV-010). Неделя — 7 колонок-дней (от xl), ниже xl дни идут столбиком.
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var string $baseUrl /admin/bookings или /specialist
 * @var array<string, array{date: string, weekday: string, isToday: bool, bookings: list<array<string, mixed>>}> $days
 * @var string $weekFrom
 * @var string $weekLabel
 * @var string $prevWeekUrl
 * @var string $nextWeekUrl
 * @var string $todayUrl
 * @var list<array{id: int, name: string}> $specialists Пусто — у Специалиста фильтра нет
 * @var int|null $specialistId
 * @var string|null $success
 * @var string|null $error
 */

ob_start();
?>
<div class="d-md-flex d-block align-items-center justify-content-between my-4">
    <div>
        <h1 class="mb-0">Записи</h1>
        <p class="mb-0 text-muted">Неделя <?= e($weekLabel) ?></p>
    </div>
    <div class="mt-3 mt-md-0 d-flex flex-wrap gap-2">
        <a href="/admin/bookings/new" class="btn btn-primary btn-sm">Новая запись</a>
        <a href="/admin/time-off" class="btn btn-outline-primary btn-sm">Закрытие слотов</a>
        <a href="<?= e($prevWeekUrl) ?>" class="btn btn-outline-secondary btn-sm">&larr; Предыдущая</a>
        <a href="<?= e($todayUrl) ?>" class="btn btn-outline-secondary btn-sm">Сегодня</a>
        <a href="<?= e($nextWeekUrl) ?>" class="btn btn-outline-secondary btn-sm">Следующая &rarr;</a>
    </div>
</div>

<?php if ($success !== null): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= e($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"><i class="fe fe-x" aria-hidden="true"></i></button>
    </div>
<?php endif; ?>
<?php if ($error !== null): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"><i class="fe fe-x" aria-hidden="true"></i></button>
    </div>
<?php endif; ?>

<?php if ($specialists !== []): ?>
    <form method="get" action="<?= e($baseUrl) ?>" class="card card-body mb-4">
        <input type="hidden" name="week" value="<?= e($weekFrom) ?>">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-6 col-lg-4">
                <label for="booking-filter-specialist" class="form-label">Специалист</label>
                <select id="booking-filter-specialist" name="specialist" class="form-select">
                    <option value="">Все Специалисты</option>
                    <?php foreach ($specialists as $specialist): ?>
                        <option value="<?= (int) $specialist['id'] ?>"<?= $specialistId === $specialist['id'] ? ' selected' : '' ?>>
                            <?= e($specialist['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-auto">
                <button type="submit" class="btn btn-primary">Показать</button>
            </div>
        </div>
    </form>
<?php endif; ?>

<div class="row g-2 booking-week">
    <?php foreach ($days as $day): ?>
        <section class="col-12 col-xl booking-week__col">
            <div class="booking-day<?= $day['isToday'] ? ' booking-day--today' : '' ?>">
                <h2 class="booking-day__header">
                    <span class="booking-day__weekday"><?= e($day['weekday']) ?></span>
                    <span class="booking-day__date"><?= e($day['date']) ?></span>
                </h2>
                <?php if ($day['bookings'] === []): ?>
                    <p class="booking-day__empty">Записей нет</p>
                <?php else: ?>
                    <ul class="booking-day__list">
                        <?php foreach ($day['bookings'] as $booking): ?>
                            <li>
                                <a href="/admin/bookings/<?= (int) $booking['id'] ?>" class="booking-tile">
                                    <span class="booking-tile__time"><?= e(substr((string) $booking['scheduled_at'], 11, 5)) ?></span>
                                    <span class="booking-tile__pet"><?= e((string) $booking['pet_name']) ?></span>
                                    <span class="booking-tile__client"><?= e((string) $booking['customer_name']) ?></span>
                                    <span class="booking-tile__services"><?= e((string) $booking['service_names']) ?></span>
                                    <?php if ($specialists !== []): ?>
                                        <span class="booking-tile__specialist"><?= e((string) $booking['specialist_name']) ?></span>
                                    <?php endif; ?>
                                    <span class="booking-tile__status">
                                        <?php $badgeStatus = (string) $booking['status']; ?>
                                        <?php include __DIR__ . '/../components/admin/booking-status-badge.php'; ?>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
