<?php

declare(strict_types=1);

/**
 * Запись на услуги — /booking (phase-4.md, Таск 3; FR-SV-001–004).
 * Экрана записи в макете нет (screens.md SCR-11, Q-053) — форма собрана
 * на компонентах темы Patte. Список Услуг рендерится здесь, на сервере;
 * Специалистов и слоты подгружает public/assets/js/booking.js из
 * /booking/specialists и /booking/slots. Форма уходит в POST /booking
 * (Таск 4): три разных поля — `_csrf`, `form_token` (антибот), honeypot.
 *
 * @var array<int, array<string, mixed>> $services    BookingController: активные Услуги
 * @var bool                             $isCustomer  авторизован как Покупатель
 * @var array<int, array<string, mixed>> $pets        Питомцы Покупателя (пусто для Гостя)
 * @var array<int, string>               $speciesList виды животных
 * @var string                           $dateMin     первая доступная дата, Y-m-d
 * @var string                           $dateMax     последняя доступная дата, Y-m-d
 * @var string                           $formToken   антибот-токен показа формы
 * @var string|null                      $notice      flash 'booking_notice'
 * @var string|null                      $error       flash 'booking_error'
 */

$pageTitle = seoTitle('booking');
$pageDescription = seoDescription('booking');
$footerVariant = 'catalog';

$bannerTitle = 'Запись на услуги';
$breadcrumbs = [
    ['name' => 'Главная', 'url' => '/'],
    ['name' => 'Запись на услуги', 'url' => null],
];

$kindTitles = ['grooming' => 'Груминг', 'vet' => 'Ветеринария'];
$servicesByKind = [];
foreach ($services as $service) {
    $servicesByKind[(string) $service['kind']][] = $service;
}

ob_start();
include __DIR__ . '/components/page-banner.php';
?>
<section class="gap">
    <div class="container">
        <form class="booking-form" id="booking-form" method="post" action="/booking"
              data-date-min="<?= e($dateMin) ?>" data-date-max="<?= e($dateMax) ?>">
            <?= csrfField() ?>
            <input type="text" name="website" class="form-honeypot" tabindex="-1" autocomplete="off" aria-hidden="true">
            <input type="hidden" name="form_token" value="<?= e($formToken) ?>">
            <div class="row">
                <div class="col-lg-8">
                    <?php if ($error !== null): ?>
                        <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
                    <?php elseif ($notice !== null): ?>
                        <div class="alert alert-info" role="status"><?= e($notice) ?></div>
                    <?php endif; ?>
                    <div id="booking-alert" class="alert alert-danger d-none" role="alert"></div>

                    <fieldset class="booking-form__step" id="booking-step-services">
                        <legend class="booking-form__title">1. Услуги</legend>
                        <p class="booking-form__hint">Можно выбрать несколько — они пройдут подряд в одном визите.</p>
                        <?php if ($services === []): ?>
                            <p>Услуги пока недоступны для записи.</p>
                        <?php endif; ?>
                        <?php foreach ($servicesByKind as $kind => $kindServices): ?>
                            <h2 class="booking-form__group"><?= e($kindTitles[$kind] ?? $kind) ?></h2>
                            <?php foreach ($kindServices as $service): ?>
                                <?php $serviceId = (int) $service['id']; ?>
                                <div class="booking-service">
                                    <input type="checkbox" class="booking-service__input" name="services[]"
                                           id="service-<?= $serviceId ?>" value="<?= $serviceId ?>">
                                    <label class="booking-service__label" for="service-<?= $serviceId ?>">
                                        <span class="booking-service__name"><?= e((string) $service['name']) ?></span>
                                        <span class="booking-service__meta">
                                            <?= (int) $service['duration_minutes'] ?> мин ·
                                            <?= e(cartFormatMoney((string) $service['price'])) ?> ₽
                                            <?php if ($service['deposit_amount'] !== null): ?>
                                                · депозит <?= e(cartFormatMoney((string) $service['deposit_amount'])) ?> ₽
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </fieldset>

                    <fieldset class="booking-form__step d-none" id="booking-step-specialist">
                        <legend class="booking-form__title">2. Специалист</legend>
                        <div id="booking-specialists" aria-live="polite"></div>
                    </fieldset>

                    <fieldset class="booking-form__step d-none" id="booking-step-time">
                        <legend class="booking-form__title">3. Дата и время</legend>
                        <label class="booking-form__label" for="booking-date">Дата</label>
                        <input type="date" class="input-text" name="date" id="booking-date"
                               min="<?= e($dateMin) ?>" max="<?= e($dateMax) ?>">
                        <div id="booking-slots" class="booking-slots" aria-live="polite"></div>
                    </fieldset>

                    <fieldset class="booking-form__step d-none" id="booking-step-pet">
                        <legend class="booking-form__title">4. Питомец</legend>
                        <?php if ($isCustomer && $pets !== []): ?>
                            <?php foreach ($pets as $index => $pet): ?>
                                <div class="booking-pet">
                                    <input type="radio" name="pet_id" id="pet-<?= (int) $pet['id'] ?>"
                                           value="<?= (int) $pet['id'] ?>"<?= $index === 0 ? ' checked' : '' ?>
                                           data-label="<?= e((string) $pet['name']) ?>">
                                    <label for="pet-<?= (int) $pet['id'] ?>">
                                        <?= e((string) $pet['name']) ?>, <?= e((string) $pet['species']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                            <div class="booking-pet">
                                <input type="radio" name="pet_id" id="pet-new" value="new" data-label="Новый питомец">
                                <label for="pet-new">Другой питомец</label>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="pet_id" value="new">
                        <?php endif; ?>

                        <div id="booking-new-pet"<?= $isCustomer && $pets !== [] ? ' class="d-none"' : '' ?>>
                            <input type="text" class="input-text" name="pet_name" id="pet-name"
                                   placeholder="Кличка *" maxlength="60">
                            <label class="booking-form__label" for="pet-species">Вид животного *</label>
                            <select name="pet_species" id="pet-species">
                                <option value="">Выберите вид</option>
                                <?php foreach ($speciesList as $species): ?>
                                    <option value="<?= e($species) ?>"><?= e($species) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" class="input-text" name="pet_breed" placeholder="Порода" maxlength="80">
                            <input type="text" class="input-text" name="pet_weight" placeholder="Вес, кг" inputmode="decimal">
                        </div>
                    </fieldset>

                    <?php if (!$isCustomer): ?>
                        <fieldset class="booking-form__step d-none" id="booking-step-contacts">
                            <legend class="booking-form__title">5. Ваши контакты</legend>
                            <p class="checkout-login-hint">
                                Уже записывались? <a href="/login">Войдите</a> — данные подставятся автоматически.
                            </p>
                            <input type="text" class="input-text" name="contact_name" placeholder="Имя *" maxlength="150">
                            <input type="tel" class="input-text" name="contact_phone" placeholder="Телефон *" maxlength="20">
                            <input type="email" class="input-text" name="contact_email" placeholder="Email *" maxlength="255">
                        </fieldset>
                    <?php endif; ?>
                </div>

                <div class="col-lg-4">
                    <aside class="booking-summary" aria-labelledby="booking-summary-title">
                        <h2 class="booking-summary__title" id="booking-summary-title">Ваша запись</h2>
                        <dl class="booking-summary__list">
                            <dt>Услуги</dt>
                            <dd id="booking-summary-services">—</dd>
                            <dt>Специалист</dt>
                            <dd id="booking-summary-specialist">—</dd>
                            <dt>Время</dt>
                            <dd id="booking-summary-time">—</dd>
                            <dt>Питомец</dt>
                            <dd id="booking-summary-pet">—</dd>
                        </dl>
                        <button type="submit" class="button" id="booking-submit" disabled>Записаться</button>
                    </aside>
                </div>
            </div>
        </form>
    </div>
</section>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/layouts/public.php';
