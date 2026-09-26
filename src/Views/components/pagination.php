<?php

declare(strict_types=1);

/**
 * Пагинация листинга каталога — сохраняет сортировку в ссылках (FR-CAT-005).
 * @var int    $page       Текущая страница (с 1)
 * @var int    $totalPages
 * @var string $basePath   Базовый URL категории, без query-строки
 * @var string $sort       Текущая сортировка
 */

if ($totalPages <= 1) {
    return;
}

$pageUrl = static function (int $targetPage) use ($basePath, $sort): string {
    $query = [];
    if ($sort !== 'popularity') {
        $query['sort'] = $sort;
    }
    if ($targetPage > 1) {
        $query['page'] = $targetPage;
    }

    return $query === [] ? $basePath : $basePath . '?' . http_build_query($query);
};
?>
<nav aria-label="Страницы каталога" class="d-flex justify-content-center" id="catalog-pagination">
    <ul class="pagination m-auto">
        <li class="prev<?= $page <= 1 ? ' disabled' : '' ?>">
            <?php if ($page > 1): ?>
                <a href="<?= e($pageUrl($page - 1)) ?>"><i class="fa-solid fa-arrow-left"></i></a>
            <?php else: ?>
                <span><i class="fa-solid fa-arrow-left"></i></span>
            <?php endif; ?>
        </li>
        <?php for ($number = 1; $number <= $totalPages; $number++): ?>
            <li class="<?= $number === $page ? 'active' : '' ?>">
                <?php if ($number === $page): ?>
                    <a href="<?= e($pageUrl($number)) ?>" aria-current="page"><?= $number ?></a>
                <?php else: ?>
                    <a href="<?= e($pageUrl($number)) ?>"><?= $number ?></a>
                <?php endif; ?>
            </li>
        <?php endfor; ?>
        <li class="next<?= $page >= $totalPages ? ' disabled' : '' ?>">
            <?php if ($page < $totalPages): ?>
                <a href="<?= e($pageUrl($page + 1)) ?>"><i class="fa-solid fa-arrow-right"></i></a>
            <?php else: ?>
                <span><i class="fa-solid fa-arrow-right"></i></span>
            <?php endif; ?>
        </li>
    </ul>
</nav>
