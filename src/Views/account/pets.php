<?php

declare(strict_types=1);

/**
 * Питомцы Покупателя — /account/pets и /account/pets/{id}/edit
 * (`FR-ACC-003`, phase-4.md, Таск 2). Одна страница на список и форму:
 * `$editPet === null` — добавление, иначе правка.
 * @var array<int, array<string, mixed>> $pets        Питомцы Покупателя
 * @var array<int, list<array<string, mixed>>> $pendingBookings Записи, ждущие оплаты Депозита, по id Питомца
 * @var array<string, mixed>|null        $editPet     Редактируемый Питомец
 * @var array<string, string>            $values      Значения полей формы
 * @var array<string, string>            $errors      Ошибки по полям
 * @var array<int, string>               $speciesList Допустимые виды животных
 * @var string|null                      $success     getFlash('success')
 * @var string|null                      $error       getFlash('error')
 */

$pageTitle = seoTitle('generic');
$pageDescription = seoDescription('generic');
$footerVariant = 'catalog';

$isEdit = $editPet !== null;
$formAction = $isEdit ? '/account/pets/' . (int) $editPet['id'] : '/account/pets';

$bannerTitle = 'Мои питомцы';
$breadcrumbs = [
    ['name' => 'Главная', 'url' => '/'],
    ['name' => 'Личный кабинет', 'url' => '/account'],
    ['name' => 'Мои питомцы', 'url' => null],
];

ob_start();
include __DIR__ . '/../components/page-banner.php';
?>
<section class="gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-3">
                <?php $accountActive = 'pets'; include __DIR__ . '/../components/account-nav.php'; ?>
            </div>
            <div class="col-lg-9">
                <?php if ($success !== null): ?>
                    <div class="alert alert-success" role="status"><?= e($success) ?></div>
                <?php endif; ?>
                <?php if ($error !== null): ?>
                    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-xl-7">
                        <h2 class="account-heading">Список питомцев</h2>
                        <?php if ($pets === []): ?>
                            <p>Вы ещё не добавили ни одного питомца.</p>
                        <?php else: ?>
                            <?php foreach ($pets as $pet): ?>
                                <article class="account-card pet-card">
                                    <h3 class="account-card__title"><?= e((string) $pet['name']) ?></h3>
                                    <dl class="pet-card__details">
                                        <dt>Вид</dt>
                                        <dd><?= e((string) $pet['species']) ?></dd>
                                        <?php if ($pet['breed'] !== null): ?>
                                            <dt>Порода</dt>
                                            <dd><?= e((string) $pet['breed']) ?></dd>
                                        <?php endif; ?>
                                        <?php if ($pet['weight'] !== null): ?>
                                            <dt>Вес</dt>
                                            <dd><?= e(rtrim(rtrim((string) $pet['weight'], '0'), '.')) ?> кг</dd>
                                        <?php endif; ?>
                                    </dl>
                                    <?php foreach ($pendingBookings[(int) $pet['id']] ?? [] as $pending): ?>
                                        <?php
                                        $pendingAt = new DateTimeImmutable((string) $pending['scheduled_at']);
                                        $pendingUntil = new DateTimeImmutable((string) $pending['slot_hold_expires_at']);
                                        ?>
                                        <div class="booking-done__notice" role="status">
                                            <p>
                                                <strong>Ждёт оплаты:</strong> запись №<?= (int) $pending['id'] ?> на
                                                <?= e($pendingAt->format('d.m.Y')) ?> в <?= e($pendingAt->format('H:i')) ?>.
                                                Депозит <?= e(cartFormatMoney((string) $pending['deposit_amount'])) ?> ₽,
                                                время закреплено до <?= e($pendingUntil->format('H:i')) ?>.
                                            </p>
                                        </div>
                                        <div class="pet-card__actions">
                                            <a class="button" href="/booking/<?= (int) $pending['id'] ?>/pay">Оплатить</a>
                                            <form method="post" action="/booking/<?= (int) $pending['id'] ?>/release">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="return" value="pets">
                                                <button type="submit" class="pet-card__delete">Отменить запись</button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="pet-card__actions">
                                        <a class="button" href="/account/pets/<?= (int) $pet['id'] ?>/edit">Изменить</a>
                                        <form method="post" action="/account/pets/<?= (int) $pet['id'] ?>/delete">
                                            <?= csrfField() ?>
                                            <button type="submit" class="pet-card__delete">Удалить</button>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="col-xl-5">
                        <div class="box login pet-form">
                            <h2 class="account-heading"><?= $isEdit ? 'Изменить питомца' : 'Добавить питомца' ?></h2>
                            <form method="post" action="<?= e($formAction) ?>" novalidate>
                                <?= csrfField() ?>

                                <label for="pet-name">Кличка</label>
                                <input type="text" name="name" id="pet-name" maxlength="60" required
                                       class="<?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                                       value="<?= e($values['name']) ?>">
                                <?php if (isset($errors['name'])): ?>
                                    <div class="invalid-feedback d-block"><?= e($errors['name']) ?></div>
                                <?php endif; ?>

                                <label for="pet-species">Вид животного</label>
                                <select name="species" id="pet-species" required
                                        class="<?= isset($errors['species']) ? 'is-invalid' : '' ?>">
                                    <option value="">Выберите вид</option>
                                    <?php foreach ($speciesList as $species): ?>
                                        <option value="<?= e($species) ?>"<?= $values['species'] === $species ? ' selected' : '' ?>><?= e($species) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['species'])): ?>
                                    <div class="invalid-feedback d-block"><?= e($errors['species']) ?></div>
                                <?php endif; ?>

                                <label for="pet-breed">Порода</label>
                                <input type="text" name="breed" id="pet-breed" maxlength="80"
                                       class="<?= isset($errors['breed']) ? 'is-invalid' : '' ?>"
                                       value="<?= e($values['breed']) ?>">
                                <?php if (isset($errors['breed'])): ?>
                                    <div class="invalid-feedback d-block"><?= e($errors['breed']) ?></div>
                                <?php endif; ?>

                                <label for="pet-weight">Вес, кг</label>
                                <input type="text" name="weight" id="pet-weight" inputmode="decimal"
                                       class="<?= isset($errors['weight']) ? 'is-invalid' : '' ?>"
                                       value="<?= e($values['weight']) ?>">
                                <?php if (isset($errors['weight'])): ?>
                                    <div class="invalid-feedback d-block"><?= e($errors['weight']) ?></div>
                                <?php endif; ?>

                                <button type="submit" class="button"><?= $isEdit ? 'Сохранить' : 'Добавить' ?></button>
                                <?php if ($isEdit): ?>
                                    <a href="/account/pets">Отмена</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/public.php';
