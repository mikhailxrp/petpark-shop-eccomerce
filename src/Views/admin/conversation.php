<?php

declare(strict_types=1);

/**
 * Переписка по Обращению — /admin/inbox/{id} (phase-5.md, Таск 7;
 * FR-CHANNELS-001) + ответ из панели и опрос новых сообщений (Таск 8,
 * FR-CHANNELS-002).
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
<script type="module" src="/admin/js/inbox.js"></script>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
