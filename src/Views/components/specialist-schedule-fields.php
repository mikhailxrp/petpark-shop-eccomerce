<?php

declare(strict_types=1);

/**
 * Блок «Услуги и график Специалиста»: форма нового сотрудника
 * (`admin/staff-form`) и правка профиля (`admin/staff-edit`), phase-7.md,
 * Таски 6 и 13.
 *
 * @var array<string, mixed> $values work_start, work_end, day_off, service_ids
 * @var array<string, string> $errors Ошибки по полям
 * @var list<array<string, mixed>> $services Активные Услуги
 */

const STAFF_DAY_OFF_LABELS = [
    1 => 'Понедельник',
    2 => 'Вторник',
    3 => 'Среда',
    4 => 'Четверг',
    5 => 'Пятница',
    6 => 'Суббота',
    0 => 'Воскресенье',
];
?>
        <fieldset class="mt-4" id="staff-specialist-fields" data-specialist-role="specialist">
            <legend class="h5">Услуги и график Специалиста</legend>
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <label for="staff-work-start" class="form-label">Начало работы</label>
                    <input type="time" class="form-control<?= isset($errors['work_start']) ? ' is-invalid' : '' ?>" id="staff-work-start" name="work_start" value="<?= e((string) $values['work_start']) ?>">
                    <?php if (isset($errors['work_start'])): ?>
                        <div class="invalid-feedback"><?= e($errors['work_start']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-6 col-md-3">
                    <label for="staff-work-end" class="form-label">Конец работы</label>
                    <input type="time" class="form-control<?= isset($errors['work_end']) ? ' is-invalid' : '' ?>" id="staff-work-end" name="work_end" value="<?= e((string) $values['work_end']) ?>">
                    <?php if (isset($errors['work_end'])): ?>
                        <div class="invalid-feedback"><?= e($errors['work_end']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-12 col-md-6">
                    <label for="staff-day-off" class="form-label">Выходной</label>
                    <select id="staff-day-off" name="day_off" class="form-select<?= isset($errors['day_off']) ? ' is-invalid' : '' ?>">
                        <option value="">Без выходного</option>
                        <?php foreach (STAFF_DAY_OFF_LABELS as $dayNumber => $dayLabel): ?>
                            <option value="<?= $dayNumber ?>"<?= (string) $dayNumber === (string) $values['day_off'] ? ' selected' : '' ?>><?= e($dayLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['day_off'])): ?>
                        <div class="invalid-feedback"><?= e($errors['day_off']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-12">
                    <span class="form-label d-block" id="staff-services-label">Услуги</span>
                    <div role="group" aria-labelledby="staff-services-label">
                        <?php foreach ($services as $service): ?>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input<?= isset($errors['service_ids']) ? ' is-invalid' : '' ?>" id="staff-service-<?= (int) $service['id'] ?>" name="service_ids[]" value="<?= (int) $service['id'] ?>"<?= in_array((int) $service['id'], $values['service_ids'], true) ? ' checked' : '' ?>>
                                <label class="form-check-label" for="staff-service-<?= (int) $service['id'] ?>"><?= e((string) $service['name']) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($services === []): ?>
                        <p class="text-muted mb-0">Активных Услуг нет.</p>
                    <?php endif; ?>
                    <?php if (isset($errors['service_ids'])): ?>
                        <div class="text-danger small mt-1"><?= e($errors['service_ids']) ?></div>
                    <?php endif; ?>
                    <div class="form-text">Без Услуг Специалист сохранится, но в форме Записи не появится.</div>
                </div>
            </div>
        </fieldset>
