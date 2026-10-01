<?php

declare(strict_types=1);

/**
 * Черновики Характеристик — /admin/ai/attributes/drafts (phase-5.md, Таск 4;
 * FR-AI-001). Только Владелец. Подтверждение пишет значение в product_attributes.
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var array<int, array{id: int, name: string}> $products Товары страницы
 * @var array<int, array<int, array{id: int, product_id: int, attr_name: string, attr_value: string|null, status: string}>> $draftsByProduct
 * @var array<string, list<string>> $dictionary attr_name => значения справочника
 * @var int $page
 * @var int $totalPages
 * @var int $total
 * @var string $decideUrl
 * @var string $draftsUrl
 * @var int $maxLength
 * @var string|null $success
 * @var string|null $error
 * @var int $errorDraftId Черновик, чья форма не прошла проверку
 */

$pageUrl = static fn (int $targetPage): string => $draftsUrl . ($targetPage > 1 ? '?page=' . $targetPage : '');
$dictionaryIds = array_flip(array_keys($dictionary));

ob_start();
?>
<div class="d-md-flex d-block align-items-center justify-content-between my-4">
    <div>
        <h1 class="mb-0">Черновики Характеристик</h1>
        <p class="mb-0 text-muted">Товаров на проверке: <?= $total ?>. В каталог значение попадает только после подтверждения.</p>
    </div>
    <a href="/admin/ai/attributes" class="btn btn-outline-secondary mt-3 mt-md-0">К разбору</a>
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

<?php foreach ($dictionary as $attrName => $values): ?>
    <datalist id="ai-dictionary-<?= $dictionaryIds[$attrName] ?>">
        <?php foreach ($values as $known): ?>
            <option value="<?= e($known) ?>"></option>
        <?php endforeach; ?>
    </datalist>
<?php endforeach; ?>

<?php if ($products === []): ?>
    <div class="card">
        <div class="card-body">
            <p class="mb-0 text-muted">Нет черновиков на проверку.</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($products as $product): ?>
        <section class="card" aria-labelledby="ai-product-<?= (int) $product['id'] ?>">
            <div class="card-header">
                <h2 class="card-title" id="ai-product-<?= (int) $product['id'] ?>"><?= e((string) $product['name']) ?></h2>
            </div>
            <div class="card-body">
                <?php foreach ($draftsByProduct[(int) $product['id']] ?? [] as $draft): ?>
                    <?php
                    $draftId = (int) $draft['id'];
                    $attrName = (string) $draft['attr_name'];
                    $isInvalid = $draftId === $errorDraftId;
                    $inputId = 'ai-value-' . $draftId;
                    ?>
                    <div class="row align-items-start gy-2 py-3 border-bottom">
                        <div class="col-12 col-lg-4">
                            <p class="mb-1 text-muted"><?= e($attrName) ?></p>
                            <p class="mb-1 fs-5"><?= e((string) $draft['attr_value']) ?></p>
                            <?php if ($draft['status'] === 'needs_decision'): ?>
                                <span class="badge bg-warning">Значения нет в справочнике</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-lg-5">
                            <form method="post" action="<?= e($decideUrl) ?>" novalidate>
                                <?= csrfField() ?>
                                <input type="hidden" name="draft_id" value="<?= $draftId ?>">
                                <input type="hidden" name="page" value="<?= $page ?>">
                                <input type="hidden" name="action" value="edit">
                                <label class="visually-hidden" for="<?= e($inputId) ?>">Исправленное значение: <?= e($attrName) ?></label>
                                <div class="input-group">
                                    <input type="text" class="form-control<?= $isInvalid ? ' is-invalid' : '' ?>"
                                           id="<?= e($inputId) ?>" name="value" maxlength="<?= $maxLength ?>"
                                           list="ai-dictionary-<?= $dictionaryIds[$attrName] ?? 0 ?>"
                                           value="<?= e((string) $draft['attr_value']) ?>"
                                           autocomplete="off" required<?= $isInvalid ? ' aria-invalid="true"' : '' ?>>
                                    <button type="submit" class="btn btn-outline-primary">Поправить</button>
                                </div>
                            </form>
                        </div>
                        <div class="col-12 col-lg-3 d-flex gap-2">
                            <form method="post" action="<?= e($decideUrl) ?>">
                                <?= csrfField() ?>
                                <input type="hidden" name="draft_id" value="<?= $draftId ?>">
                                <input type="hidden" name="page" value="<?= $page ?>">
                                <input type="hidden" name="action" value="confirm">
                                <button type="submit" class="btn btn-success">Подтвердить</button>
                            </form>
                            <form method="post" action="<?= e($decideUrl) ?>">
                                <?= csrfField() ?>
                                <input type="hidden" name="draft_id" value="<?= $draftId ?>">
                                <input type="hidden" name="page" value="<?= $page ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-outline-danger">Отклонить</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <?php if ($totalPages > 1): ?>
        <nav aria-label="Страницы черновиков">
            <ul class="pagination justify-content-center">
                <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                    <a class="page-link" href="<?= e($pageUrl(max(1, $page - 1))) ?>">Назад</a>
                </li>
                <?php for ($number = 1; $number <= $totalPages; $number++): ?>
                    <li class="page-item<?= $number === $page ? ' active' : '' ?>">
                        <a class="page-link" href="<?= e($pageUrl($number)) ?>"<?= $number === $page ? ' aria-current="page"' : '' ?>><?= $number ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item<?= $page >= $totalPages ? ' disabled' : '' ?>">
                    <a class="page-link" href="<?= e($pageUrl(min($totalPages, $page + 1))) ?>">Вперёд</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
