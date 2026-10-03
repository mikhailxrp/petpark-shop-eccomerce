<?php

declare(strict_types=1);

/**
 * Список статических страниц — /admin/pages (phase-8.md, Таск 9; ADR-008).
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var array<int, array{id: int, slug: string, title: string, updated_at: string}> $pages
 * @var string|null $success
 * @var string|null $error
 */

ob_start();
?>
<div class="d-md-flex d-block align-items-center justify-content-between my-4">
    <div>
        <h1 class="mb-0">Страницы</h1>
        <p class="mb-0 text-muted">Тексты и SEO-поля статических страниц сайта</p>
    </div>
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

<div class="card">
    <div class="card-header"><h2 class="card-title">Все страницы</h2></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th scope="col">Название</th>
                        <th scope="col">Адрес</th>
                        <th scope="col">Изменена</th>
                        <th scope="col"><span class="visually-hidden">Действия</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pages as $page): ?>
                        <tr>
                            <td><?= e((string) $page['title']) ?></td>
                            <td><a href="/<?= e((string) $page['slug']) ?>" target="_blank" rel="noopener">/<?= e((string) $page['slug']) ?></a></td>
                            <td><?= e((string) $page['updated_at']) ?></td>
                            <td class="text-end">
                                <a href="/admin/pages/<?= (int) $page['id'] ?>" class="btn btn-outline-primary btn-sm">Изменить</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../../layouts/admin.php';
