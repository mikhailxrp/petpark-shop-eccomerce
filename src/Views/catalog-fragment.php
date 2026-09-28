<?php

declare(strict_types=1);

/**
 * AJAX-фрагмент листинга каталога — та же разметка правой колонки, что
 * и в catalog.php (components/catalog-results.php), но без <head>/шапки/
 * футера: ответ на fetch() из public/assets/js/catalog.js при смене
 * сортировки без перезагрузки страницы. Возвращается вместо catalog.php,
 * когда CatalogController::show() видит заголовок X-Requested-With.
 * @var array<int, array<string, mixed>> $products
 * @var int    $total
 * @var string $sort
 * @var int    $page
 * @var int    $totalPages
 * @var string $actionPath Базовый URL без query — /catalog[/{cat}[/{sub}]] или /search (Таск 3)
 * @var array<string, mixed> $queryState Текущие фильтры/поиск (Таск 3)
 */

include __DIR__ . '/components/catalog-results.php';
