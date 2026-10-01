<?php

declare(strict_types=1);

/**
 * Разбор Характеристик — /admin/ai/attributes (phase-5.md, Таск 3; FR-AI-001).
 * Только Владелец. Порции запускает ai-batch.js.
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var array{queue: int, processed: int, pending: int, needs_decision: int} $counts
 * @var array<int, array{id: int, name: string, description: string|null}> $queue Начало очереди
 * @var string $runUrl
 * @var string $draftsUrl Экран подтверждения черновиков
 * @var bool $allowed Помощник не приостановлен лимитом
 */

ob_start();
?>
<div class="my-4">
    <h1 class="mb-0">Разбор Характеристик</h1>
    <p class="mb-0 text-muted">ИИ извлекает вид животного, возраст и назначение из описаний Товаров. Результат — черновики: в каталог они не попадают, пока Владелец их не подтвердит.</p>
</div>

<?php if (!$allowed): ?>
    <div class="alert alert-warning" role="alert">Разбор Характеристик приостановлен: достигнут месячный лимит расхода на ИИ.</div>
<?php endif; ?>

<div class="row">
    <div class="col-6 col-lg-3">
        <section class="card" aria-labelledby="ai-batch-queue-title">
            <div class="card-body">
                <h2 class="fs-6 text-muted" id="ai-batch-queue-title">В очереди</h2>
                <p class="fs-4 mb-0" id="ai-batch-queue"><?= $counts['queue'] ?></p>
            </div>
        </section>
    </div>
    <div class="col-6 col-lg-3">
        <section class="card" aria-labelledby="ai-batch-done-title">
            <div class="card-body">
                <h2 class="fs-6 text-muted" id="ai-batch-done-title">Обработано Товаров</h2>
                <p class="fs-4 mb-0" id="ai-batch-done"><?= $counts['processed'] ?></p>
            </div>
        </section>
    </div>
    <div class="col-6 col-lg-3">
        <section class="card" aria-labelledby="ai-batch-pending-title">
            <div class="card-body">
                <h2 class="fs-6 text-muted" id="ai-batch-pending-title">Ожидают подтверждения</h2>
                <p class="fs-4 mb-0" id="ai-batch-pending"><?= $counts['pending'] ?></p>
            </div>
        </section>
    </div>
    <div class="col-6 col-lg-3">
        <section class="card" aria-labelledby="ai-batch-decision-title">
            <div class="card-body">
                <h2 class="fs-6 text-muted" id="ai-batch-decision-title">Требуют решения</h2>
                <p class="fs-4 mb-0" id="ai-batch-decision"><?= $counts['needs_decision'] ?></p>
            </div>
        </section>
    </div>
</div>

<section class="card" aria-labelledby="ai-batch-run-title">
    <div class="card-header"><h2 class="card-title" id="ai-batch-run-title">Запуск</h2></div>
    <div class="card-body">
        <form id="ai-batch-form" method="post" action="<?= e($runUrl) ?>" data-run-url="<?= e($runUrl) ?>">
            <?= csrfField() ?>
            <button type="submit" class="btn btn-primary" id="ai-batch-start"<?= $allowed && $counts['queue'] > 0 ? '' : ' disabled' ?>>Запустить разбор</button>
            <button type="button" class="btn btn-outline-secondary" id="ai-batch-stop" hidden>Остановить</button>
            <a href="<?= e($draftsUrl) ?>" class="btn btn-outline-primary">Проверить черновики (<span id="ai-batch-open-drafts"><?= $counts['pending'] + $counts['needs_decision'] ?></span>)</a>
        </form>
        <p class="mb-0 mt-3 text-muted" id="ai-batch-status" role="status" aria-live="polite">
            Товары обрабатываются порциями, пока эта страница открыта. Закроете страницу — необработанные останутся в очереди.
        </p>
    </div>
</section>

<section class="card" aria-labelledby="ai-batch-queue-list-title">
    <div class="card-header"><h2 class="card-title" id="ai-batch-queue-list-title">Начало очереди</h2></div>
    <div class="card-body">
        <?php if ($queue === []): ?>
            <p class="mb-0 text-muted">Необработанных Товаров нет.</p>
        <?php else: ?>
            <ul class="mb-0">
                <?php foreach ($queue as $product): ?>
                    <li><?= e((string) $product['name']) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<script type="module" src="/admin/js/ai-batch.js"></script>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
