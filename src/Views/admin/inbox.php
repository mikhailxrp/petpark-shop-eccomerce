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

<div class="alert alert-info" role="status">
    Демо: интеграции с каналами не подключены, переписка — тестовые данные.
</div>

<section class="card" aria-labelledby="inbox-title">
    <div class="card-header"><h2 class="card-title" id="inbox-title">Список Обращений (<?= $total ?>)</h2></div>
    <div class="card-body p-0">
        <?php if ($conversations === []): ?>
            <p class="m-3 text-muted">Обращений пока нет.</p>
        <?php else: ?>
            <ul class="inbox-list list-unstyled mb-0">
                <?php foreach ($conversations as $item): ?>
                    <li>
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
        <?php endif; ?>
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
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
