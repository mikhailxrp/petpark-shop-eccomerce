<?php

declare(strict_types=1);

/**
 * Карточка условия на страницах «Доставка» и «Оплата»: иконка, заголовок,
 * плашка-акцент и текст/список. Колонка row — здесь же, внешний row — во View.
 * @var array{icon: string, title: string, badge: string, text: string, items: array<int, string>} $infoCard
 */
?>
<div class="col-lg-6 info-card-col">
    <article class="info-card">
        <div class="info-card__head">
            <span class="info-card__icon" aria-hidden="true"><i class="fa-solid <?= e($infoCard['icon']) ?>"></i></span>
            <span class="info-card__badge"><?= e($infoCard['badge']) ?></span>
        </div>
        <h2 class="info-card__title"><?= e($infoCard['title']) ?></h2>
        <p class="info-card__text"><?= e($infoCard['text']) ?></p>
        <?php if ($infoCard['items'] !== []): ?>
            <ul class="info-card__list">
                <?php foreach ($infoCard['items'] as $item): ?>
                    <li><?= e($item) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>
</div>
