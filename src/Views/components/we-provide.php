<?php

declare(strict_types=1);

/**
 * Карточка «We Provide» блока «О компании» (about.html, 3 шт.).
 * @var array{title: string, text: string, href: string, image: string, alt: string, ring: string} $provide
 */
?>
<div class="col-lg-4 col-md-6">
    <div class="we-provide">
        <div class="we-provide-img">
            <img src="<?= e($provide['image']) ?>" alt="<?= e($provide['alt']) ?>">
            <?php $ringSize = 326; $ringFill = $provide['ring']; include __DIR__ . '/ring-svg.php'; ?>
        </div>
        <a href="<?= e($provide['href']) ?>"><h5><?= e($provide['title']) ?></h5></a>
        <p><?= e($provide['text']) ?></p>
    </div>
</div>
