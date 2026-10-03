<?php

declare(strict_types=1);

/**
 * Акцентная полоса под карточками условий («Бесплатная доставка», «Резерв»).
 * @var array{icon: string, title: string, text: string} $infoBanner
 */
?>
<div class="col-12">
    <aside class="info-banner">
        <span class="info-banner__icon" aria-hidden="true"><i class="fa-solid <?= e($infoBanner['icon']) ?>"></i></span>
        <div class="info-banner__body">
            <h2 class="info-banner__title"><?= e($infoBanner['title']) ?></h2>
            <p class="info-banner__text"><?= e($infoBanner['text']) ?></p>
        </div>
    </aside>
</div>
