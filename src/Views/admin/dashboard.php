<?php

declare(strict_types=1);

/**
 * Пустая сводка после входа персонала — /admin и /specialist
 * (phase-1.md, Таск 7). Реальные данные (заказы, календарь, финотчёты,
 * модерация отзывов) появятся в Тасках/Фазах 8/3/7 — здесь только
 * подтверждение, что вход и ролевой доступ работают.
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 */

ob_start();
?>
<div class="d-md-flex d-block align-items-center justify-content-between my-4">
    <div>
        <h1 class="mb-0">Добро пожаловать!</h1>
        <p class="mb-0 text-muted">Роль: <?= e($roleLabel) ?></p>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <p class="mb-0">Сводка появится по мере готовности разделов панели.</p>
    </div>
</div>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
