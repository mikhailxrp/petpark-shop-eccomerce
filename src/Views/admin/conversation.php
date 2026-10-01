<?php

declare(strict_types=1);

/**
 * Переписка по Обращению — /admin/inbox/{id} (phase-5.md, Таск 7;
 * FR-CHANNELS-001). Формы ответа нет — Таск 8.
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var string $channel
 * @var string $sender
 * @var bool $identified Отправитель опознан как Покупатель
 * @var string $contact Телефон/идентификатор отправителя в Канале
 * @var bool $wasUnread Обращение было непрочитанным до открытия
 * @var array<int, array{incoming: bool, body: string, time: string}> $messages
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

<div class="alert alert-info" role="status">
    Демо: интеграции с каналами не подключены, переписка — тестовые данные.
</div>

<section class="card" aria-labelledby="conversation-title">
    <div class="card-header"><h2 class="card-title" id="conversation-title">Переписка</h2></div>
    <div class="card-body">
        <div class="chat-thread">
            <?php foreach ($messages as $message): ?>
                <div class="chat-bubble <?= $message['incoming'] ? 'chat-bubble--in' : 'chat-bubble--out' ?>">
                    <span class="visually-hidden"><?= $message['incoming'] ? 'Входящее' : 'Исходящее' ?>:</span>
                    <?= nl2br(e($message['body'])) ?>
                    <span class="chat-bubble__time"><?= e($message['time']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
