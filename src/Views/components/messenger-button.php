<?php

declare(strict_types=1);

/**
 * Плавающая кнопка перехода в мессенджер на всех публичных страницах
 * (FR-NOTIF-003). Только переход в диалог: приём сообщений — модуль CHANNELS.
 * Все доступные мессенджеры видны сразу колонкой иконок, без JS.
 * Нет ни одного мессенджера — блока нет.
 *
 * @var list<array{code: string, label: string, url: string}> $messengerLinks Готовит layouts/public.php
 */
$messengerLinks ??= [];
?>
<?php if ($messengerLinks !== []): ?>
    <nav class="messenger-button" id="messenger-button" aria-label="Написать в мессенджер">
        <ul class="messenger-button__list" id="messenger-list">
            <?php foreach ($messengerLinks as $link): ?>
                <li class="messenger-button__item">
                    <a class="messenger-button__link" href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer"
                       aria-label="Написать в <?= e($link['label']) ?>" title="<?= e($link['label']) ?>">
                        <span class="messenger-button__icon">
                            <?php $messengerCode = $link['code']; include __DIR__ . '/messenger-icon.php'; ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
<?php endif; ?>
