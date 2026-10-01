<?php

declare(strict_types=1);

/**
 * Запись по звонку — /admin/bookings/new (phase-4.md, Таск 8; FR-SV-010).
 * Специалистов, слоты и Питомцев клиента подгружает admin-booking.js с
 * сервера; Запись создаётся сразу подтверждённой, без Депозита.
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var array<int, array<string, mixed>> $services
 * @var list<string> $speciesList
 * @var string $dateMin
 * @var string $dateMax
 * @var string $backUrl
 * @var array<string, mixed> $form введённое до ошибки валидации
 * @var string|null $error
 */

$value = static fn (string $key): string => e((string) ($form[$key] ?? ''));
$checkedServices = array_map('strval', (array) ($form['services'] ?? []));

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
        <h1 class="mb-0">Новая запись</h1>
        <p class="mb-0 text-muted">Запись по звонку создаётся сразу подтверждённой, Депозит не требуется.</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary btn-sm">К календарю</a>
    </div>
</div>

<form
    method="post"
    action="/admin/bookings"
    id="booking-manual-form"
    class="needs-validation"
    novalidate
    data-specialists-url="/admin/bookings/specialists"
    data-slots-url="/admin/bookings/slots"
    data-pets-url="/admin/bookings/pets"
    data-initial-pet="<?= $value('pet_id') !== '' ? $value('pet_id') : 'new' ?>"
    data-initial-specialist="<?= $value('specialist_id') ?>"
    data-initial-slot="<?= $value('slot') ?>"
>
    <?= csrfField() ?>

    <div class="row">
        <div class="col-12 col-lg-6">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Клиент</h2></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="booking-contact-name" class="form-label">Имя</label>
                        <input type="text" class="form-control" id="booking-contact-name" name="contact_name" maxlength="150" value="<?= $value('contact_name') ?>" required>
                        <div class="invalid-feedback">Укажите имя.</div>
                    </div>
                    <div class="mb-3">
                        <label for="booking-contact-phone" class="form-label">Телефон</label>
                        <input type="tel" class="form-control" id="booking-contact-phone" name="contact_phone" maxlength="20" value="<?= $value('contact_phone') ?>" required>
                        <div class="invalid-feedback">Укажите телефон.</div>
                    </div>
                    <div class="mb-0">
                        <label for="booking-contact-email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="booking-contact-email" name="contact_email" maxlength="255" value="<?= $value('contact_email') ?>" required>
                        <div class="invalid-feedback">Укажите корректный email.</div>
                        <div class="form-text">Если клиент с таким email уже есть, Запись привяжется к нему, иначе будет создан аккаунт.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Питомец</h2></div>
                <div class="card-body">
                    <fieldset class="mb-3">
                        <legend class="form-label fs-14">Выбор</legend>
                        <div id="booking-pets"></div>
                    </fieldset>
                    <div id="booking-new-pet">
                        <div class="mb-3">
                            <label for="booking-pet-name" class="form-label">Кличка</label>
                            <input type="text" class="form-control" id="booking-pet-name" name="pet_name" maxlength="60" value="<?= $value('pet_name') ?>" required>
                            <div class="invalid-feedback">Укажите кличку.</div>
                        </div>
                        <div class="mb-3">
                            <label for="booking-pet-species" class="form-label">Вид животного</label>
                            <select class="form-select" id="booking-pet-species" name="pet_species" required>
                                <?php foreach ($speciesList as $species): ?>
                                    <option value="<?= e($species) ?>"<?= ($form['pet_species'] ?? '') === $species ? ' selected' : '' ?>><?= e($species) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-2">
                            <div class="col-12 col-sm-8">
                                <label for="booking-pet-breed" class="form-label">Порода</label>
                                <input type="text" class="form-control" id="booking-pet-breed" name="pet_breed" maxlength="80" value="<?= $value('pet_breed') ?>">
                            </div>
                            <div class="col-12 col-sm-4">
                                <label for="booking-pet-weight" class="form-label">Вес, кг</label>
                                <input type="text" class="form-control" id="booking-pet-weight" name="pet_weight" inputmode="decimal" maxlength="6" value="<?= $value('pet_weight') ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="card-title">Услуги</h2></div>
        <div class="card-body">
            <fieldset class="mb-0">
                <legend class="visually-hidden">Услуги</legend>
                <?php foreach ($services as $service): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="services[]" id="booking-service-<?= (int) $service['id'] ?>" value="<?= (int) $service['id'] ?>"<?= in_array((string) $service['id'], $checkedServices, true) ? ' checked' : '' ?>>
                        <label class="form-check-label" for="booking-service-<?= (int) $service['id'] ?>">
                            <?= e((string) $service['name']) ?> — <?= (int) $service['duration_minutes'] ?> мин, <?= e((string) $service['price']) ?> ₽
                        </label>
                    </div>
                <?php endforeach; ?>
            </fieldset>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="card-title">Специалист и время</h2></div>
        <div class="card-body">
            <fieldset class="mb-3">
                <legend class="form-label fs-14">Специалист</legend>
                <div id="booking-specialists" aria-live="polite"></div>
            </fieldset>
            <div class="mb-3">
                <label for="booking-date" class="form-label">Дата</label>
                <input type="date" class="form-control w-auto" id="booking-date" name="date" min="<?= e($dateMin) ?>" max="<?= e($dateMax) ?>" value="<?= $value('date') ?>" required>
                <div class="invalid-feedback">Выберите дату.</div>
            </div>
            <fieldset class="mb-0">
                <legend class="form-label fs-14">Время</legend>
                <div id="booking-slots" aria-live="polite"></div>
            </fieldset>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary" id="booking-submit" disabled>Создать запись</button>
            <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary ms-2">Отмена</a>
        </div>
    </div>
</form>
<script type="module" src="/admin/js/admin-booking.js"></script>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
