<?php

declare(strict_types=1);

/**
 * Юридический документ — /privacy, /offer (phase-8.md, Таск 2, FR-CNT-013).
 * Структура — баннер и секция текста, как у `about.html` (Q-046: макета
 * документов нет). Заголовок — `content_pages.title`, текст — `body`
 * (уже очищено contentHtmlSanitize()).
 * @var array<string, mixed> $page
 * @var string               $bodyHtml очищенный `body`
 */

$breadcrumbs = [
    ['name' => 'Главная', 'url' => '/'],
    ['name' => (string) $page['title'], 'url' => null],
];
$bannerTitle = (string) $page['title'];

ob_start();
?>
<?php include __DIR__ . '/components/page-banner.php'; ?>

<section class="gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-10">
                <article class="legal-page">
                    <div class="legal-page__body"><?= $bodyHtml ?></div>
                </article>
            </div>
        </div>
    </div>
</section>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/layouts/public.php';
