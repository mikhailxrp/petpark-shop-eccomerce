<?php

declare(strict_types=1);

/**
 * Карточка услуги блока «Pet Care Services» (about.html, 4 шт.).
 * @var array{title: string, text: string, href: string, icon: string} $service
 */
?>
<div class="col-lg-3 p-lg-0 col-md-6 col-sm-6">
    <div class="pet-grooming">
        <i><img src="<?= e($service['icon']) ?>" alt=""></i>
        <?php $ringSize = 138; $ringFill = '#940c69'; include __DIR__ . '/ring-svg.php'; ?>
        <a href="<?= e($service['href']) ?>"><h4><?= e($service['title']) ?></h4></a>
        <p><?= e($service['text']) ?></p>
    </div>
</div>
