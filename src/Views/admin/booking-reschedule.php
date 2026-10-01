<?php

declare(strict_types=1);

/**
 * Перенос Записи — /admin/bookings/{id}/reschedule (phase-4.md, Таск 8;
 * FR-SV-010). Отмена + новая Запись; Услуги, Питомец и Депозит переходят
 * на новую. Слоты подгружает admin-booking.js.
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var array<string, mixed> $booking bookingFindForStaff()
 * @var list<array{id: int, name: string}> $specialists
 * @var string $dateMin
 * @var string $dateMax
 * @var string|null $error
 */

$bookingId = (int) $booking['id'];
$cardUrl = '/admin/bookings/' . $bookingId;

ob_start();
?>
<?php if ($error !== null): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"><i class="fe fe-x" aria-hidden="true"></i></button>
    </div>
<?php endif; ?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4">
    <div>
        <h1 class="mb-0">Перенос записи №<?= $bookingId ?></h1>
        <p class="mb-0 text-muted">
            Сейчас: <?= e(date('d.m.Y H:i', (int) strtotime((string) $booking['scheduled_at']))) ?>,
            <?= e((string) $booking['specialist_name']) ?>, <?= e((string) $booking['pet_name']) ?>
        </p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="<?= e($cardUrl) ?>" class="btn btn-outline-secondary btn-sm">К Записи</a>
    </div>
</div>

<form
    method="post"
    action="<?= e($cardUrl) ?>/reschedule"
    id="booking-reschedule-form"
    class="needs-validation"
    novalidate
    data-slots-url="/admin/bookings/slots"
    data-booking-id="<?= $bookingId ?>"
>
    <?= csrfField() ?>

    <div class="card">
        <div class="card-header"><h2 class="card-title">Новое время</h2></div>
        <div class="card-body">
            <fieldset class="mb-3">
                <legend class="form-label fs-14">Специалист</legend>
                <div id="booking-specialists">
                    <?php foreach ($specialists as $specialist): ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="specialist_id" id="booking-specialist-<?= (int) $specialist['id'] ?>" value="<?= (int) $specialist['id'] ?>"<?= $specialist['id'] === (int) $booking['specialist_id'] || count($specialists) === 1 ? ' checked' : '' ?>>
                            <label class="form-check-label" for="booking-specialist-<?= (int) $specialist['id'] ?>"><?= e($specialist['name']) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <div class="mb-3">
                <label for="booking-date" class="form-label">Дата</label>
                <input type="date" class="form-control w-auto" id="booking-date" name="date" min="<?= e($dateMin) ?>" max="<?= e($dateMax) ?>" required>
                <div class="invalid-feedback">Выберите дату.</div>
            </div>
            <fieldset class="mb-0">
                <legend class="form-label fs-14">Время</legend>
                <div id="booking-slots" aria-live="polite"></div>
            </fieldset>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary" id="booking-submit" disabled>Перенести</button>
            <a href="<?= e($cardUrl) ?>" class="btn btn-outline-secondary ms-2">Отмена</a>
        </div>
    </div>
</form>
<script type="module" src="/admin/js/admin-booking.js"></script>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
