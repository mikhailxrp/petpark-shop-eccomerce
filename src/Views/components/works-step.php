<?php

declare(strict_types=1);

/**
 * Шаг блока «Как мы работаем» (how-we-works.html, 3 шт.).
 * @var array{number: int, title: string, text: string, icon: string, modifier: string} $step modifier: ''|'two'
 */
?>
<div class="col-lg-6">
    <div class="works pages<?= $step['modifier'] === 'two' ? ' two' : '' ?>">
        <div class="works-img">
            <i><img src="<?= e($step['icon']) ?>" alt=""></i>
            <span><?= (int) $step['number'] ?></span>
        </div>
        <div>
            <h4><?= e($step['title']) ?></h4>
            <p><?= e($step['text']) ?></p>
        </div>
    </div>
</div>
