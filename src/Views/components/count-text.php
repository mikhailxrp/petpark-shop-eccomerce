<?php

declare(strict_types=1);

/**
 * Счётчик блока «Fun facts» (about.html, 4 шт.). Число анимирует
 * custom.js (секция «11. count») по data-number.
 * @var array{number: int, suffix: string, label: string, icon: string} $stat
 */
?>
<div class="col-lg-3 col-md-4 col-sm-6">
    <div class="count-text">
        <img alt="" src="<?= e($stat['icon']) ?>">
        <div>
            <div class="d-flex justify-content-center">
                <p class="count count-text__number" data-number="<?= (int) $stat['number'] ?>"><?= (int) $stat['number'] ?></p>
                <span><?= e($stat['suffix']) ?></span>
            </div>
            <p class="text count-text__label"><?= e($stat['label']) ?></p>
        </div>
    </div>
</div>
