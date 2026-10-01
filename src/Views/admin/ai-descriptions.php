<?php

declare(strict_types=1);

/**
 * Генерация описаний — /admin/ai/descriptions (phase-5.md, Таск 5; FR-AI-002).
 * Только Владелец. Пакет запускает ai-batch.js; черновик виден только здесь.
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var array<int, array{id: int, name: string, parent_id: ?int}> $categories
 * @var int $categoryId 0 — все Категории
 * @var bool $draftsOnly
 * @var array<int, array{id: int, name: string, description: string|null, description_draft: string|null, category_name: string}> $products
 * @var int $page
 * @var int $totalPages
 * @var int $total
 * @var array{queue: int, processed: int} $counts
 * @var bool $allowed Помощник не приостановлен лимитом
 * @var string $pageUrl
 * @var string $runUrl
 * @var string $generateUrl
 * @var string $publishUrl
 * @var string $discardUrl
 * @var int $maxLength
 * @var int $excerptLength
 * @var string|null $success
 * @var string|null $error
 * @var int $errorProductId Товар, чья форма не прошла проверку
 */

$listUrl = static function (int $targetPage) use ($pageUrl, $categoryId, $draftsOnly): string {
    $query = array_filter([
        'category_id' => $categoryId,
        'filter'      => $draftsOnly ? 'drafts' : '',
        'page'        => $targetPage > 1 ? $targetPage : 0,
    ]);

    return $pageUrl . ($query === [] ? '' : '?' . http_build_query($query));
};

ob_start();
?>
<div class="my-4">
    <h1 class="mb-0">Генерация описаний</h1>
    <p class="mb-0 text-muted">ИИ пишет черновик описания по названию, Категории, бренду и подтверждённым Характеристикам. Черновик виден только здесь — в карточке Товара текст появится после публикации.</p>
</div>

<?php if ($success !== null): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= e($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"><i class="fe fe-x" aria-hidden="true"></i></button>
    </div>
<?php endif; ?>
<?php if ($error !== null): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"><i class="fe fe-x" aria-hidden="true"></i></button>
    </div>
<?php endif; ?>
<?php if (!$allowed): ?>
    <div class="alert alert-warning" role="alert" data-alert-persist>Генерация описаний приостановлена: достигнут месячный лимит расхода на ИИ.</div>
<?php endif; ?>

<section class="card" aria-labelledby="ai-batch-run-title">
    <div class="card-header"><h2 class="card-title" id="ai-batch-run-title">Пакет по Категории</h2></div>
    <div class="card-body">
        <form method="get" action="<?= e($pageUrl) ?>" class="row gy-2 align-items-end mb-3">
            <div class="col-12 col-md-6">
                <label for="ai-category" class="form-label">Категория</label>
                <select class="form-select" id="ai-category" name="category_id">
                    <option value="0">Все Категории</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"<?= (int) $category['id'] === $categoryId ? ' selected' : '' ?>><?= e((string) $category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="ai-filter-drafts" name="filter" value="drafts"<?= $draftsOnly ? ' checked' : '' ?>>
                    <label class="form-check-label" for="ai-filter-drafts">Только с черновиками</label>
                </div>
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-outline-secondary w-100">Показать</button>
            </div>
        </form>

        <p class="mb-2">
            В очереди выбранной Категории: <strong id="ai-batch-queue"><?= $counts['queue'] ?></strong>.
            Всего черновиков на проверке: <strong id="ai-batch-done"><?= $counts['processed'] ?></strong>.
        </p>

        <form id="ai-batch-form" method="post" action="<?= e($runUrl) ?>" data-run-url="<?= e($runUrl) ?>">
            <?= csrfField() ?>
            <input type="hidden" name="category_id" value="<?= $categoryId ?>">
            <button type="submit" class="btn btn-primary" id="ai-batch-start"<?= $allowed && $counts['queue'] > 0 ? '' : ' disabled' ?>>Сгенерировать для Категории</button>
            <button type="button" class="btn btn-outline-secondary" id="ai-batch-stop" hidden>Остановить</button>
        </form>
        <p class="mb-0 mt-3 text-muted" id="ai-batch-status" role="status" aria-live="polite">
            <?php if ($categoryId === 0): ?>
                Выберите Категорию, чтобы запустить пакет. Товары обрабатываются порциями, пока эта страница открыта.
            <?php else: ?>
                Товары обрабатываются порциями, пока эта страница открыта. Закроете страницу — необработанные останутся в очереди.
            <?php endif; ?>
        </p>
    </div>
</section>

<?php if ($products === []): ?>
    <div class="card">
        <div class="card-body">
            <p class="mb-0 text-muted"><?= $draftsOnly ? 'Нет черновиков на проверку.' : 'Товаров не найдено.' ?></p>
        </div>
    </div>
<?php else: ?>
    <p class="text-muted">Товаров: <?= $total ?></p>
    <?php foreach ($products as $product): ?>
        <?php
        $productId = (int) $product['id'];
        $currentText = trim((string) ($product['description'] ?? ''));
        $draftText = $product['description_draft'];
        $isInvalid = $productId === $errorProductId;
        $textareaId = 'ai-draft-' . $productId;
        ?>
        <section class="card" aria-labelledby="ai-product-<?= $productId ?>">
            <div class="card-header">
                <h2 class="card-title" id="ai-product-<?= $productId ?>"><?= e((string) $product['name']) ?></h2>
                <span class="text-muted ms-auto"><?= e((string) $product['category_name']) ?></span>
            </div>
            <div class="card-body">
                <p class="mb-1 text-muted">Сейчас в карточке</p>
                <p class="mb-3"><?= $currentText === '' ? 'Описания нет.' : e(mb_strimwidth($currentText, 0, $excerptLength, '…')) ?></p>

                <?php if ($draftText !== null): ?>
                    <form method="post" action="<?= e($publishUrl) ?>" novalidate>
                        <?= csrfField() ?>
                        <input type="hidden" name="product_id" value="<?= $productId ?>">
                        <input type="hidden" name="category_id" value="<?= $categoryId ?>">
                        <input type="hidden" name="filter" value="<?= $draftsOnly ? 'drafts' : '' ?>">
                        <input type="hidden" name="page" value="<?= $page ?>">
                        <label class="form-label" for="<?= e($textareaId) ?>">Черновик (можно править)</label>
                        <textarea class="form-control mb-3<?= $isInvalid ? ' is-invalid' : '' ?>" id="<?= e($textareaId) ?>"
                                  name="text" rows="6" maxlength="<?= $maxLength ?>" required<?= $isInvalid ? ' aria-invalid="true"' : '' ?>><?= e((string) $draftText) ?></textarea>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-success">Опубликовать</button>
                            <button type="submit" class="btn btn-outline-danger" formaction="<?= e($discardUrl) ?>" formnovalidate>Отклонить</button>
                        </div>
                    </form>
                <?php endif; ?>

                <form method="post" action="<?= e($generateUrl) ?>" class="mt-3">
                    <?= csrfField() ?>
                    <input type="hidden" name="product_id" value="<?= $productId ?>">
                    <input type="hidden" name="category_id" value="<?= $categoryId ?>">
                    <input type="hidden" name="filter" value="<?= $draftsOnly ? 'drafts' : '' ?>">
                    <input type="hidden" name="page" value="<?= $page ?>">
                    <button type="submit" class="btn btn-outline-primary"<?= $allowed ? '' : ' disabled' ?>><?= $draftText === null ? 'Сгенерировать' : 'Сгенерировать заново' ?></button>
                </form>
            </div>
        </section>
    <?php endforeach; ?>

    <?php if ($totalPages > 1): ?>
        <nav aria-label="Страницы Товаров">
            <ul class="pagination justify-content-center">
                <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                    <a class="page-link" href="<?= e($listUrl(max(1, $page - 1))) ?>">Назад</a>
                </li>
                <?php for ($number = 1; $number <= $totalPages; $number++): ?>
                    <li class="page-item<?= $number === $page ? ' active' : '' ?>">
                        <a class="page-link" href="<?= e($listUrl($number)) ?>"<?= $number === $page ? ' aria-current="page"' : '' ?>><?= $number ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item<?= $page >= $totalPages ? ' disabled' : '' ?>">
                    <a class="page-link" href="<?= e($listUrl(min($totalPages, $page + 1))) ?>">Вперёд</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<script type="module" src="/admin/js/ai-batch.js"></script>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
