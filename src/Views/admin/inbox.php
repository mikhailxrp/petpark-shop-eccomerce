<?php

declare(strict_types=1);

/**
 * Единый инбокс — /admin/inbox (phase-5.md, Таск 7; FR-CHANNELS-001).
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var array<int, array{id: int, channel: string, sender: string, identified: bool, preview: string, time: string, unread: bool}> $conversations
 * @var int $page
 * @var int $totalPages
 * @var int $total
 * @var int $perPage
 * @var string $pollUrl
 * @var int $pollIntervalMs
 * @var int $sinceMessageId
 * @var string $simulateUrl
 * @var array<string, string> $simulateChannels Включённые Каналы: код => название
 * @var string|null $success
 * @var string|null $error
 */

$pageUrl = static fn (int $targetPage): string => '/admin/inbox' . ($targetPage > 1 ? '?page=' . $targetPage : '');

// Компактная пагинация: 1 … текущая±1 … последняя (null = многоточие).
$pageWindow = [];
$previous = 0;
foreach (array_unique([1, $page - 1, $page, $page + 1, $totalPages]) as $number) {
    if ($number < 1 || $number > $totalPages) {
        continue;
    }
    if ($number - $previous > 1) {
        $pageWindow[] = null;
    }
    $pageWindow[] = $number;
    $previous = $number;
}

ob_start();
?>
<div class="my-4">
    <h1 class="mb-0">Обращения</h1>
    <p class="mb-0 text-muted">Сообщения из MAX, Telegram, ВКонтакте и Avito в одном списке.</p>
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

<?php if ($simulateChannels !== []): ?>
    <form method="post" action="<?= e($simulateUrl) ?>" class="row g-2 align-items-end mb-3" id="inbox-simulate">
        <?= csrfField() ?>
        <div class="col-12 col-sm-auto">
            <label for="inbox-simulate-channel" class="form-label mb-1">Канал</label>
            <select name="channel" id="inbox-simulate-channel" class="form-select">
                <?php foreach ($simulateChannels as $code => $label): ?>
                    <option value="<?= e($code) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-sm-auto">
            <button type="submit" class="btn btn-outline-primary">Сымитировать входящее</button>
        </div>
    </form>
<?php endif; ?>

<section class="card" aria-labelledby="inbox-title"<?php if ($page === 1): ?>
    data-inbox-poll
    data-poll-url="<?= e($pollUrl) ?>"
    data-interval="<?= $pollIntervalMs ?>"
    data-since="<?= $sinceMessageId ?>"
    data-per-page="<?= $perPage ?>"<?php endif; ?>>
    <div class="card-header"><h2 class="card-title" id="inbox-title">Список Обращений (<span id="inbox-total"><?= $total ?></span>)</h2></div>
    <div class="card-body p-0">
        <p class="m-3 text-muted" id="inbox-empty"<?= $conversations === [] ? '' : ' hidden' ?>>Обращений пока нет.</p>
        <ul class="inbox-list list-unstyled mb-0" id="inbox-list">
                <?php foreach ($conversations as $item): ?>
                    <li data-conversation-id="<?= $item['id'] ?>">
                        <a href="/admin/inbox/<?= $item['id'] ?>" class="inbox-item<?= $item['unread'] ? ' inbox-item--unread' : '' ?>">
                            <span class="badge bg-light text-dark inbox-item__channel"><?= e($item['channel']) ?></span>
                            <span class="inbox-item__body">
                                <span class="inbox-item__sender">
                                    <?= e($item['sender']) ?>
                                    <?php if ($item['identified']): ?>
                                        <span class="badge bg-success-transparent">Покупатель</span>
                                    <?php endif; ?>
                                    <?php if ($item['unread']): ?>
                                        <span class="badge bg-primary">Непрочитано</span>
                                    <?php endif; ?>
                                </span>
                                <span class="inbox-item__preview"><?= e($item['preview']) ?></span>
                            </span>
                            <span class="inbox-item__time"><?= e($item['time']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
        </ul>
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <nav aria-label="Страницы списка Обращений">
                <ul class="pagination pagination-sm justify-content-center flex-wrap mb-0">
                    <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                        <a class="page-link" href="<?= e($pageUrl(max(1, $page - 1))) ?>" aria-label="Назад"><span aria-hidden="true">&lsaquo;</span></a>
                    </li>
                    <?php foreach ($pageWindow as $number): ?>
                        <?php if ($number === null): ?>
                            <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                        <?php else: ?>
                            <li class="page-item<?= $number === $page ? ' active' : '' ?>">
                                <a class="page-link" href="<?= e($pageUrl($number)) ?>"<?= $number === $page ? ' aria-current="page"' : '' ?>><?= $number ?></a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <li class="page-item<?= $page >= $totalPages ? ' disabled' : '' ?>">
                        <a class="page-link" href="<?= e($pageUrl(min($totalPages, $page + 1))) ?>" aria-label="Вперёд"><span aria-hidden="true">&rsaquo;</span></a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</section>
<script type="module" src="/admin/js/inbox.js"></script>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
