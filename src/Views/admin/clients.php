<?php

declare(strict_types=1);

/**
 * Список клиентов — /admin/clients (phase-7.md, Таск 11; FR-MGR-002).
 * Таблица по паттерну списка Заказов (admin-assembly.md).
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var array<int, array<string, mixed>> $clients clientList()
 * @var string $query Поисковый запрос как введён
 * @var int $page
 * @var int $totalPages
 * @var int $total
 */

$pageUrl = static function (int $targetPage) use ($query): string {
    $params = [];
    if ($query !== '') {
        $params['q'] = $query;
    }
    if ($targetPage > 1) {
        $params['page'] = $targetPage;
    }

    return '/admin/clients' . ($params === [] ? '' : '?' . http_build_query($params));
};

ob_start();
?>
<div class="d-md-flex d-block align-items-center justify-content-between my-4">
    <div>
        <h1 class="mb-0">Клиенты</h1>
        <p class="mb-0 text-muted">Найдено: <?= $total ?></p>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <form method="get" action="/admin/clients" class="row g-2 align-items-center w-100">
            <div class="col-12 col-md-6">
                <label for="clients-q" class="visually-hidden">Имя или телефон</label>
                <input type="search" id="clients-q" name="q" class="form-control"
                       placeholder="Имя или телефон" maxlength="<?= CLIENT_SEARCH_MAX_LENGTH ?>"
                       value="<?= e($query) ?>">
            </div>
            <div class="col-12 col-md-auto">
                <button type="submit" class="btn btn-primary">Найти</button>
                <?php if ($query !== ''): ?>
                    <a href="/admin/clients" class="btn btn-outline-secondary">Сбросить</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <div class="card-body">
        <?php if ($clients === []): ?>
            <p class="mb-0 text-muted">Клиентов не найдено.</p>
        <?php else: ?>
            <div class="table-responsive position-relative">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Клиент</th>
                            <th scope="col">Телефон</th>
                            <th scope="col">Питомцы</th>
                            <th scope="col">Клиент с</th>
                            <th scope="col"><span class="visually-hidden">Действия</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $row): ?>
                            <tr>
                                <th scope="row">
                                    <?= e((string) $row['name']) ?>
                                    <div class="text-muted fs-12 fw-normal"><?= e((string) $row['email']) ?></div>
                                </th>
                                <td><?= ($row['phone'] ?? '') !== '' ? e((string) $row['phone']) : '—' ?></td>
                                <td><?= ($row['pet_names'] ?? '') !== '' ? e((string) $row['pet_names']) : '—' ?></td>
                                <td><?= e(date('d.m.Y', (int) strtotime((string) $row['created_at']))) ?></td>
                                <td><a href="/admin/clients/<?= (int) $row['id'] ?>" class="btn btn-sm btn-outline-primary">Открыть</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <nav aria-label="Страницы списка клиентов">
                <ul class="pagination justify-content-center mb-0">
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
        </div>
    <?php endif; ?>
</div>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
