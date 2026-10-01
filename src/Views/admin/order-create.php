<?php

declare(strict_types=1);

/**
 * Ручное создание Заказа — /admin/orders/new (phase-3.md, Таск 5;
 * FR-ORD-003). Состав собирает admin-order-create.js: поиск Варианта
 * (/admin/orders/variants), строки `items[N][variant_id|quantity]`.
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var array<string, string> $form введённое до ошибки валидации
 * @var int|null $conversationId Обращение-источник (FR-CHANNELS-003)
 * @var string $source draft — по черновику ИИ, manual — вручную
 * @var array<int, array<string, mixed>> $oldLines Позиции до ошибки (variant_id, quantity, name, sku, price)
 * @var string $checkoutToken одноразовый токен идемпотентности (FR-CHK-007)
 * @var string $freeThreshold порог бесплатной доставки курьером (BR-006)
 * @var string $courierCost стоимость курьерской доставки (BR-006)
 * @var string|null $error
 */

$value = static fn (string $key): string => e($form[$key] ?? '');
$deliveryMethod = $form['delivery_method'] ?? 'pickup';
$paymentMethod = $form['payment_method'] ?? 'cash_or_card_on_delivery';

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
        <h1 class="mb-0">Новый заказ</h1>
        <p class="mb-0 text-muted">Заказ создаётся в статусе «Новый», товар резервируется на <?= ORDER_RESERVE_MINUTES ?> мин.</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="/admin/orders" class="btn btn-outline-secondary btn-sm">К списку</a>
    </div>
</div>

<?php if ($conversationId !== null): ?>
    <div class="alert alert-info" role="status">
        Из Обращения <a href="/admin/inbox/<?= $conversationId ?>">№<?= $conversationId ?></a>.
        <?= $source === 'draft' ? 'Позиции предложены ИИ — проверьте состав и укажите email.' : 'Контакты подставлены из Обращения — укажите email и состав.' ?>
    </div>
<?php endif; ?>

<form
    method="post"
    action="/admin/orders/new"
    id="order-create-form"
    class="needs-validation"
    novalidate
    data-delivery-method="<?= e($deliveryMethod) ?>"
    data-free-threshold="<?= e($freeThreshold) ?>"
    data-courier-cost="<?= e($courierCost) ?>"
    data-initial-lines="<?= e((string) json_encode($oldLines, JSON_UNESCAPED_UNICODE)) ?>"
    data-max-quantity="<?= ORDER_ITEM_MAX_QUANTITY ?>"
>
    <?= csrfField() ?>
    <input type="hidden" name="checkout_token" value="<?= e($checkoutToken) ?>">
    <?php if ($conversationId !== null): ?>
        <input type="hidden" name="conversation_id" value="<?= $conversationId ?>">
        <input type="hidden" name="source" value="<?= e($source) ?>">
    <?php endif; ?>

    <div class="row">
        <div class="col-12 col-lg-6">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Покупатель</h2></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="contact-name" class="form-label">Имя</label>
                        <input type="text" class="form-control" id="contact-name" name="contact_name" maxlength="150" value="<?= $value('contact_name') ?>" required>
                        <div class="invalid-feedback">Укажите имя.</div>
                    </div>
                    <div class="mb-3">
                        <label for="contact-phone" class="form-label">Телефон</label>
                        <input type="tel" class="form-control" id="contact-phone" name="contact_phone" maxlength="20" value="<?= $value('contact_phone') ?>" required>
                        <div class="invalid-feedback">Укажите телефон.</div>
                    </div>
                    <div class="mb-3">
                        <label for="contact-email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="contact-email" name="contact_email" maxlength="255" value="<?= $value('contact_email') ?>" required>
                        <div class="invalid-feedback">Укажите корректный email.</div>
                        <div class="form-text">Если Покупатель с таким email уже есть, Заказ привяжется к нему, иначе будет создан аккаунт.</div>
                    </div>
                    <div class="mb-0">
                        <label for="customer-note" class="form-label">Комментарий</label>
                        <textarea class="form-control" id="customer-note" name="customer_note" rows="3" maxlength="500"><?= $value('customer_note') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Доставка и оплата</h2></div>
                <div class="card-body">
                    <fieldset class="mb-3">
                        <legend class="form-label fs-14">Получение</legend>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="delivery_method" id="delivery-pickup" value="pickup"<?= $deliveryMethod === 'pickup' ? ' checked' : '' ?> required>
                            <label class="form-check-label" for="delivery-pickup">Самовывоз из магазина</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="delivery_method" id="delivery-courier" value="courier"<?= $deliveryMethod === 'courier' ? ' checked' : '' ?>>
                            <label class="form-check-label" for="delivery-courier">Курьером по <?= e(SHOP_CITY) ?></label>
                        </div>
                    </fieldset>

                    <div id="order-create-address" class="mb-3<?= $deliveryMethod === 'courier' ? '' : ' d-none' ?>">
                        <div class="mb-2">
                            <label for="delivery-street" class="form-label">Улица</label>
                            <input type="text" class="form-control" id="delivery-street" name="delivery_street" maxlength="150" value="<?= $value('delivery_street') ?>"<?= $deliveryMethod === 'courier' ? ' required' : ' disabled' ?>>
                            <div class="invalid-feedback">Укажите улицу.</div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label for="delivery-house" class="form-label">Дом</label>
                                <input type="text" class="form-control" id="delivery-house" name="delivery_house" maxlength="30" value="<?= $value('delivery_house') ?>"<?= $deliveryMethod === 'courier' ? ' required' : ' disabled' ?>>
                                <div class="invalid-feedback">Укажите дом.</div>
                            </div>
                            <div class="col-6">
                                <label for="delivery-apartment" class="form-label">Квартира/офис</label>
                                <input type="text" class="form-control" id="delivery-apartment" name="delivery_apartment" maxlength="30" value="<?= $value('delivery_apartment') ?>"<?= $deliveryMethod === 'courier' ? '' : ' disabled' ?>>
                            </div>
                        </div>
                        <div>
                            <label for="delivery-comment" class="form-label">Комментарий курьеру</label>
                            <input type="text" class="form-control" id="delivery-comment" name="delivery_comment" maxlength="200" value="<?= $value('delivery_comment') ?>"<?= $deliveryMethod === 'courier' ? '' : ' disabled' ?>>
                        </div>
                    </div>

                    <fieldset class="mb-0">
                        <legend class="form-label fs-14">Оплата</legend>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method" id="payment-cod" value="cash_or_card_on_delivery"<?= $paymentMethod === 'cash_or_card_on_delivery' ? ' checked' : '' ?> required>
                            <label class="form-check-label" for="payment-cod">Наличными или картой при получении</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method" id="payment-card" value="card_online"<?= $paymentMethod === 'card_online' ? ' checked' : '' ?>>
                            <label class="form-check-label" for="payment-card">Картой на сайте</label>
                        </div>
                        <div class="form-text">Ссылки на оплату нет: подтвердите Заказ вручную, иначе резерв снимется через <?= ORDER_RESERVE_MINUTES ?> мин.</div>
                    </fieldset>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="card-title">Состав Заказа</h2></div>
        <div class="card-body">
            <div class="position-relative mb-3">
                <label for="variant-search" class="form-label">Найти Вариант по названию или артикулу</label>
                <input type="search" class="form-control" id="variant-search" autocomplete="off" maxlength="64" aria-controls="variant-search-results">
                <ul class="list-group variant-search__results d-none" id="variant-search-results" role="listbox" aria-label="Результаты поиска"></ul>
            </div>

            <p class="text-muted d-none" id="order-lines-empty">Добавьте хотя бы одну Позицию.</p>
            <div class="table-responsive position-relative">
                <table class="table text-nowrap align-middle" id="order-lines-table">
                    <thead>
                        <tr>
                            <th scope="col">Товар</th>
                            <th scope="col">Цена, ₽</th>
                            <th scope="col">Кол-во</th>
                            <th scope="col">Сумма, ₽</th>
                            <th scope="col"><span class="visually-hidden">Действия</span></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                    <tfoot>
                        <tr>
                            <th scope="row" colspan="3" class="text-end">Доставка</th>
                            <td colspan="2" id="order-delivery-preview">—</td>
                        </tr>
                        <tr>
                            <th scope="row" colspan="3" class="text-end">Итого</th>
                            <td colspan="2"><strong id="order-total-preview" aria-live="polite">—</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Создать заказ</button>
            <a href="/admin/orders" class="btn btn-outline-secondary ms-2">Отмена</a>
        </div>
    </div>
</form>
<script type="module" src="/assets/js/admin-order-create.js"></script>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
