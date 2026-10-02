<?php

declare(strict_types=1);

/**
 * Заявка на Возврат — /account/returns/{orderId}/new (`FR-RET-001`,
 * phase-6.md, Таск 5). Форма «причина + фото», работает без JS;
 * assets/js/return-form.js добавляет счётчик символов и список файлов.
 * @var array<string, mixed>       $order     Заказ Покупателя
 * @var list<array<string, mixed>> $items     Позиции Заказа
 * @var string                     $reason    Введённая ранее причина (после ошибки)
 * @var array<string, string>      $errors    Ошибки по полям (reason, photos)
 * @var int                        $reasonMax Лимит причины
 * @var int                        $photosMin Минимум фото
 * @var int                        $photosMax Максимум фото
 * @var int                        $photoMb   Лимит размера фото, МБ
 * @var string|null                $error     getFlash('error')
 */

$pageTitle = seoTitle('account-return-form');
$pageDescription = seoDescription('account-return-form');
$robotsNoindex = true;
$footerVariant = 'catalog';

$orderedAt = new DateTimeImmutable((string) $order['created_at']);

$bannerTitle = 'Заявка на возврат';
$breadcrumbs = [
    ['name' => 'Главная', 'url' => '/'],
    ['name' => 'Личный кабинет', 'url' => '/account'],
    ['name' => 'Возвраты', 'url' => '/account/returns'],
    ['name' => 'Заказ №' . (int) $order['id'], 'url' => null],
];

ob_start();
include __DIR__ . '/../components/page-banner.php';
?>
<section class="gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-3">
                <?php $accountActive = 'returns'; include __DIR__ . '/../components/account-nav.php'; ?>
            </div>
            <div class="col-lg-9">
                <?php if ($error !== null): ?>
                    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-xl-5">
                        <section class="account-card order-summary" aria-labelledby="order-summary-title">
                            <h2 class="account-heading" id="order-summary-title">Заказ №<?= (int) $order['id'] ?></h2>
                            <p class="order-summary__date">от <?= e($orderedAt->format('d.m.Y')) ?></p>
                            <ul class="order-summary__items">
                                <?php foreach ($items as $item): ?>
                                    <li class="order-summary__item">
                                        <?= e((string) $item['product_name']) ?>
                                        <?php if ($item['variant_label'] !== null && $item['variant_label'] !== ''): ?>
                                            (<?= e((string) $item['variant_label']) ?>)
                                        <?php endif; ?>
                                        × <?= (int) $item['quantity'] ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <p class="order-summary__total">
                                Сумма: <strong><?= e(cartFormatMoney((string) $order['total'])) ?> ₽</strong>
                            </p>
                        </section>

                        <section class="account-card" aria-labelledby="return-steps-title">
                            <h2 class="account-heading" id="return-steps-title">Как проходит возврат</h2>
                            <ol class="return-steps">
                                <li>Вы отправляете заявку с причиной и фото.</li>
                                <li>Мы рассмотрим её в течение 24 часов и напишем на email.</li>
                                <li>После одобрения вернём деньги за весь заказ.</li>
                            </ol>
                        </section>
                    </div>

                    <div class="col-xl-7">
                        <div class="box login pet-form return-form">
                            <h2 class="account-heading">Причина и фото</h2>
                            <form method="post" action="/account/returns/<?= (int) $order['id'] ?>"
                                  enctype="multipart/form-data" novalidate>
                                <?= csrfField() ?>

                                <label for="return-reason">Причина возврата</label>
                                <textarea name="reason" id="return-reason" rows="5" required
                                          maxlength="<?= (int) $reasonMax ?>"
                                          class="<?= isset($errors['reason']) ? 'is-invalid' : '' ?>"
                                          aria-describedby="return-reason-counter<?= isset($errors['reason']) ? ' return-reason-error' : '' ?>"
                                          <?= isset($errors['reason']) ? 'aria-invalid="true"' : '' ?>><?= e($reason) ?></textarea>
                                <p class="return-form__hint" id="return-reason-counter">
                                    До <?= (int) $reasonMax ?> символов
                                </p>
                                <?php if (isset($errors['reason'])): ?>
                                    <div class="invalid-feedback d-block" id="return-reason-error" role="alert"><?= e($errors['reason']) ?></div>
                                <?php endif; ?>

                                <label for="return-photos">Фото</label>
                                <input type="file" name="photos[]" id="return-photos" multiple required
                                       accept="image/jpeg,image/png,image/webp"
                                       class="return-form__file<?= isset($errors['photos']) ? ' is-invalid' : '' ?>"
                                       aria-describedby="return-photos-hint<?= isset($errors['photos']) ? ' return-photos-error' : '' ?>"
                                       <?= isset($errors['photos']) ? 'aria-invalid="true"' : '' ?>>
                                <p class="return-form__hint" id="return-photos-hint">
                                    От <?= (int) $photosMin ?> до <?= (int) $photosMax ?> фото, JPEG, PNG или WebP,
                                    каждое до <?= (int) $photoMb ?> МБ. Файлы нужно выбрать заново при каждой отправке.
                                </p>
                                <?php if (isset($errors['photos'])): ?>
                                    <div class="invalid-feedback d-block" id="return-photos-error" role="alert"><?= e($errors['photos']) ?></div>
                                <?php endif; ?>
                                <ul class="return-form__files" id="return-photos-list" aria-live="polite"></ul>

                                <button type="submit" class="button">Отправить заявку</button>
                                <a href="/account/returns">Отмена</a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<script type="module" src="/assets/js/return-form.js"></script>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/public.php';
