<?php

declare(strict_types=1);

/**
 * Плавающая кнопка перехода в мессенджер на всех публичных страницах
 * (FR-NOTIF-003). Только переход в диалог: приём сообщений — модуль CHANNELS.
 * Без JS ссылки остаются доступны списком; со скриптом (messenger-button.js)
 * список раскрывается по кнопке. Нет ни одного мессенджера — кнопки нет.
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
                    <a class="messenger-button__link" href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer">
                        <span class="messenger-button__icon">
                            <?php $messengerCode = $link['code']; include __DIR__ . '/messenger-icon.php'; ?>
                        </span>
                        <span class="messenger-button__label"><?= e($link['label']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <button class="messenger-button__toggle" id="messenger-toggle" type="button"
                aria-expanded="false" aria-controls="messenger-list" aria-label="Написать в мессенджер">
            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
        </button>
    </nav>
<?php endif; ?>
