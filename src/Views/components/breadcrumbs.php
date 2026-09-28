<?php

declare(strict_types=1);

/**
 * Хлебные крошки — та же цепочка, что и JSON-LD BreadcrumbList
 * (renderBreadcrumbSchema(), src/Core/Seo.php), одна цепочка на оба вывода.
 * @var array<int, array{name: string, url: ?string}> $breadcrumbs Главная → ... → текущая
 */
?>
<ol class="breadcrumb">
    <?php $lastIndex = array_key_last($breadcrumbs); ?>
    <?php foreach ($breadcrumbs as $index => $crumb): ?>
        <?php $isCurrent = $index === $lastIndex; ?>
        <li class="breadcrumb-item<?= $isCurrent ? ' active' : '' ?>"<?= $isCurrent ? ' aria-current="page"' : '' ?>>
            <?php if (!$isCurrent && $crumb['url'] !== null): ?>
                <a href="<?= e($crumb['url']) ?>"><?= e($crumb['name']) ?></a>
            <?php else: ?>
                <?= e($crumb['name']) ?>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ol>
