<?php

declare(strict_types=1);

/**
 * ИИ-помощники — /admin/ai (phase-5.md, Таск 2; NFR-AI §11.8).
 * Только Владелец. Промпт из журнала сюда не попадает.
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var string $spent Расход за месяц, строка DECIMAL
 * @var string $limit Лимит, строка DECIMAL
 * @var int $percent Доля лимита, %
 * @var int $barValue Доля для шкалы, не больше 100
 * @var bool $notify Достигнут порог уведомления (баннер)
 * @var array<int, array{name: string, class: string, active: bool}> $assistants
 * @var array<int, array<string, mixed>> $calls Строки журнала (подготовлены контроллером)
 * @var array{total: int, edited: int, editedPercent: int|null} $outcomes
 * @var int $page
 * @var int $totalPages
 * @var int $total
 */

$pageUrl = static fn (int $targetPage): string => '/admin/ai' . ($targetPage > 1 ? '?page=' . $targetPage : '');

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
<?php if ($notify): ?>
    <div class="alert alert-warning" role="alert">
        Расход ИИ-помощников достиг <?= $percent ?>% месячного лимита.
        <?php if ($percent >= AI_LIMIT_LIVE_PERCENT): ?>
            Все помощники приостановлены.
        <?php elseif ($percent >= AI_LIMIT_BACKGROUND_PERCENT): ?>
            Разбор Характеристик и генерация описаний приостановлены.
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="my-4">
    <h1 class="mb-0">ИИ-помощники</h1>
    <p class="mb-0 text-muted">Расход за текущий месяц, состояние помощников и журнал вызовов.</p>
</div>

<div class="row">
    <div class="col-12 col-lg-6">
        <section class="card" aria-labelledby="ai-spend-title">
            <div class="card-header"><h2 class="card-title" id="ai-spend-title">Расход и лимит</h2></div>
            <div class="card-body">
                <p class="fs-4 mb-2"><?= e(number_format((float) $spent, 2, '.', '')) ?> ₽ <span class="text-muted fs-6">из <?= e(number_format((float) $limit, 2, '.', '')) ?> ₽ (<?= $percent ?>%)</span></p>
                <progress class="w-100" max="100" value="<?= $barValue ?>" aria-label="Доля лимита расхода"><?= $barValue ?>%</progress>
                <p class="mb-0 mt-2 text-muted fs-12">
                    Уведомление Владельцу — с <?= AI_LIMIT_NOTIFY_PERCENT ?>%; стоп фоновых помощников — с <?= AI_LIMIT_BACKGROUND_PERCENT ?>%; стоп всех — с <?= AI_LIMIT_LIVE_PERCENT ?>%.
                </p>
            </div>
        </section>
    </div>
    <div class="col-12 col-lg-6">
        <section class="card" aria-labelledby="ai-edits-title">
            <div class="card-header"><h2 class="card-title" id="ai-edits-title">Доля поправленных черновиков</h2></div>
            <div class="card-body">
                <?php if ($outcomes['editedPercent'] === null): ?>
                    <p class="mb-0 text-muted">Нет данных: черновики ещё не подтверждались.</p>
                <?php else: ?>
                    <p class="fs-4 mb-0"><?= $outcomes['editedPercent'] ?>% <span class="text-muted fs-6">(<?= $outcomes['edited'] ?> из <?= $outcomes['total'] ?>)</span></p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<section class="card" aria-labelledby="ai-assistants-title">
    <div class="card-header">
        <h2 class="card-title" id="ai-assistants-title">Помощники</h2>
        <a href="/admin/ai/attributes" class="btn btn-sm btn-outline-primary ms-auto">Разбор Характеристик</a>
        <a href="/admin/ai/descriptions" class="btn btn-sm btn-outline-primary ms-2">Генерация описаний</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th scope="col">Помощник</th>
                        <th scope="col">Класс задачи</th>
                        <th scope="col">Состояние</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assistants as $assistant): ?>
                        <tr>
                            <th scope="row"><?= e($assistant['name']) ?></th>
                            <td><?= e($assistant['class']) ?></td>
                            <td>
                                <?php if ($assistant['active']): ?>
                                    <span class="badge bg-success">Включён</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Приостановлен по лимиту</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="card" aria-labelledby="ai-journal-title">
    <div class="card-header"><h2 class="card-title" id="ai-journal-title">Журнал вызовов (<?= $total ?>)</h2></div>
    <div class="card-body">
        <?php if ($calls === []): ?>
            <p class="mb-0 text-muted">Вызовов пока не было.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Дата</th>
                            <th scope="col">Помощник</th>
                            <th scope="col">Класс</th>
                            <th scope="col">Провайдер</th>
                            <th scope="col">Токены</th>
                            <th scope="col">Стоимость, ₽</th>
                            <th scope="col">Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($calls as $call): ?>
                            <tr>
                                <td class="text-nowrap"><?= e((string) $call['date']) ?></td>
                                <td><?= e((string) $call['task']) ?></td>
                                <td><?= e((string) $call['class']) ?></td>
                                <td><?= e((string) $call['provider']) ?></td>
                                <td><?= (int) $call['tokens'] ?></td>
                                <td><?= e((string) $call['cost']) ?></td>
                                <td><span class="badge bg-<?= e((string) $call['statusVariant']) ?>"><?= e((string) $call['statusLabel']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <nav aria-label="Страницы журнала вызовов">
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
