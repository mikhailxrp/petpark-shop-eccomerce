<?php

declare(strict_types=1);

/**
 * Карточка специалиста блока «Best Working Team» (about.html, 3 шт.).
 * Соцсетей у специалистов в БД нет — иконок из макета нет.
 * @var array{name: string, slug: string, position: string, photo: string} $member
 */
?>
<div class="col-lg-4 col-md-6">
    <div class="team-working">
        <a href="/team/<?= e($member['slug']) ?>">
            <img src="<?= e($member['photo']) ?>" alt="<?= e($member['name'] . ', ' . $member['position']) ?>" width="170" height="170">
        </a>
        <?php $ringSize = 188; $ringFill = '#000'; include __DIR__ . '/ring-svg.php'; ?>
        <span><?= e($member['position']) ?></span>
        <h4><a href="/team/<?= e($member['slug']) ?>"><?= e($member['name']) ?></a></h4>
    </div>
</div>
