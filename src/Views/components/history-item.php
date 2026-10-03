<?php

declare(strict_types=1);

/**
 * Строка таймлайна блока «История» (history.html).
 * @var array{year: string, title: string, text: string, modifier: string} $item modifier: ''|'color'
 * @var bool $isLast
 */
?>
<div class="history-time<?= $isLast ? ' end' : '' ?>">
    <div>
        <div class="history-data<?= $item['modifier'] === 'color' ? ' color' : '' ?>">
            <h3><?= e($item['year']) ?></h3>
        </div>
    </div>
    <div class="history-text">
        <h4><?= e($item['title']) ?></h4>
        <p><?= e($item['text']) ?></p>
    </div>
</div>
