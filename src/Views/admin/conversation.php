<?php

declare(strict_types=1);

/**
 * Переписка по Обращению — /admin/inbox/{id} (phase-5.md, Таск 7;
 * FR-CHANNELS-001) + ответ из панели и опрос новых сообщений (Таск 8,
 * FR-CHANNELS-002) + черновик Заказа от ИИ (Таск 9, FR-AI-004).
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var int $conversationId
 * @var string $channel
 * @var string $sender
 * @var bool $identified Отправитель опознан как Покупатель
 * @var string $contact Телефон/идентификатор отправителя в Канале
 * @var bool $wasUnread Обращение было непрочитанным до открытия
 * @var array<int, array{id: int, incoming: bool, body: string, time: string}> $messages
 * @var int $lastMessageId
 * @var string $pollUrl
 * @var int $pollIntervalMs
 * @var string $replyUrl
 * @var int $maxLength
 * @var string $replyDraft Текст неотправленного ответа (после ошибки)
 * @var array{items: list<array{name: string, label: string, price: string, quantity: int}>, note: string, generated_at: string}|null $orderDraft
 * @var string $draftUrl
 * @var int|null $linkedOrderId Заказ, созданный из Обращения
 * @var string $confirmDraftUrl форма Заказа по черновику ИИ
 * @var string $manualOrderUrl форма Заказа вручную
 * @var bool $hasDraftItems в черновике есть Позиции
 * @var array{total: int, confirmed: int} $attributeCoverage
 * @var string|null $success
 * @var string|null $error
 */

ob_start();
?>
<div class="my-4">
    <a href="/admin/inbox" class="btn btn-sm btn-outline-secondary mb-3">К списку Обращений</a>
    <h1 class="mb-0"><?= e($sender) ?></h1>
    <p class="mb-0 text-muted">
        <?= e($channel) ?>
        <?php if ($contact !== ''): ?>· <?= e($contact) ?><?php endif; ?>
        <?php if ($identified): ?><span class="badge bg-success-transparent">Покупатель</span><?php endif; ?>
        <?php if ($wasUnread): ?><span class="badge bg-primary">Было непрочитано</span><?php endif; ?>
    </p>
</div>

<div class="alert alert-info" role="status" data-alert-persist>
    Демо: интеграции с каналами не подключены, переписка — тестовые данные.
</div>

<?php if ($success !== null): ?>
    <div class="alert alert-success" role="status"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error !== null): ?>
    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<section class="card" aria-labelledby="conversation-title">
    <div class="card-header"><h2 class="card-title" id="conversation-title">Переписка</h2></div>
    <div class="card-body">
        <div class="chat-thread" id="chat-thread"
             data-inbox-poll
             data-poll-url="<?= e($pollUrl) ?>"
             data-interval="<?= $pollIntervalMs ?>"
             data-conversation="<?= $conversationId ?>"
             data-since="<?= $lastMessageId ?>">
            <?php foreach ($messages as $message): ?>
                <div class="chat-bubble <?= $message['incoming'] ? 'chat-bubble--in' : 'chat-bubble--out' ?>">
                    <span class="visually-hidden"><?= $message['incoming'] ? 'Входящее' : 'Исходящее' ?>:</span>
                    <?= nl2br(e($message['body'])) ?>
                    <span class="chat-bubble__time"><?= e($message['time']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card-footer">
        <form method="post" action="<?= e($replyUrl) ?>">
            <?= csrfField() ?>
            <label for="reply-body" class="form-label">Ответ в <?= e($channel) ?></label>
            <textarea name="body" id="reply-body" rows="3" maxlength="<?= $maxLength ?>"
                      class="form-control<?= $error !== null ? ' is-invalid' : '' ?>" required><?= e($replyDraft) ?></textarea>
            <div class="d-flex justify-content-between align-items-center mt-2">
                <span class="form-text">До <?= $maxLength ?> символов, только текст.</span>
                <button type="submit" class="btn btn-primary">Отправить</button>
            </div>
        </form>
    </div>
</section>

<section class="card mt-4" aria-labelledby="draft-title">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 class="card-title" id="draft-title">Черновик Заказа</h2>
        <?php if ($orderDraft !== null): ?><span class="badge bg-warning-transparent">Предложено ИИ — проверьте</span><?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($orderDraft === null): ?>
            <p class="text-muted">Черновика пока нет. ИИ предложит состав Заказа по тексту переписки; телефон и адрес в ИИ не передаются.</p>
        <?php elseif ($orderDraft['items'] === []): ?>
            <p>ИИ не нашёл подходящих Товаров в каталоге — оформите Заказ вручную из текста Обращения.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table text-nowrap">
                    <thead><tr><th scope="col">Товар</th><th scope="col">Количество</th><th scope="col">Цена, ₽</th></tr></thead>
                    <tbody>
                    <?php foreach ($orderDraft['items'] as $item): ?>
                        <tr>
                            <td><?= e($item['name']) ?><?php if ($item['label'] !== ''): ?>, <?= e($item['label']) ?><?php endif; ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td><?= e($item['price']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php if ($orderDraft !== null && $orderDraft['note'] !== ''): ?>
            <p class="mb-2"><strong>Комментарий ИИ:</strong> <?= e($orderDraft['note']) ?></p>
        <?php endif; ?>
        <dl class="row mb-2">
            <dt class="col-sm-3">Контакты</dt>
            <dd class="col-sm-9">
                <?= e($sender) ?><?php if ($contact !== ''): ?>, <?= e($contact) ?><?php endif; ?>
                <?php if ($identified): ?><span class="badge bg-success-transparent">Покупатель</span><?php endif; ?>
            </dd>
        </dl>
        <p class="form-text mb-3">
            Подбор идёт только по подтверждённым Характеристикам: они есть у
            <?= $attributeCoverage['confirmed'] ?> из <?= $attributeCoverage['total'] ?> Товаров.
            <?php if ($orderDraft !== null): ?>Черновик от <?= e($orderDraft['generated_at']) ?>.<?php endif; ?>
        </p>
        <form method="post" action="<?= e($draftUrl) ?>">
            <?= csrfField() ?>
            <button type="submit" class="btn btn-outline-primary"><?= $orderDraft === null ? 'Разобрать с ИИ' : 'Разобрать заново' ?></button>
        </form>
        <hr>
        <?php if ($linkedOrderId !== null): ?>
            <p class="mb-0">Заказ создан: <a href="/admin/orders/<?= $linkedOrderId ?>">№<?= $linkedOrderId ?></a>.</p>
        <?php else: ?>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($hasDraftItems): ?>
                    <a href="<?= e($confirmDraftUrl) ?>" class="btn btn-primary">Подтвердить черновик</a>
                <?php else: ?>
                    <span class="d-inline-block" tabindex="0" title="Сначала получите черновик с Позициями">
                        <button type="button" class="btn btn-primary" disabled>Подтвердить черновик</button>
                    </span>
                <?php endif; ?>
                <a href="<?= e($manualOrderUrl) ?>" class="btn btn-outline-secondary">Создать вручную</a>
            </div>
            <p class="form-text mb-0">Откроется форма Заказа: проверьте состав и укажите email Покупателя.</p>
        <?php endif; ?>
    </div>
</section>
<script type="module" src="/admin/js/inbox.js"></script>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
