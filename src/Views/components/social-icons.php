<?php
/**
 * Соц-иконки шапки и футера — глифы VK/Telegram/MAX (messenger-icon.php),
 * ссылки из настроек сайта (FR-NOTIF-003). Выводятся только мессенджеры из
 * CHANNELS_ENABLED с заданной ссылкой; нет ни одной — блока нет.
 *
 * @var list<array{code: string, label: string, url: string}> $messengerLinks Готовит layouts/public.php
 */
$messengerLinks ??= [];
?>
<?php if ($messengerLinks !== []): ?>
    <ul class="social-icon">
        <?php foreach ($messengerLinks as $link): ?>
            <li>
                <a href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e($link['label']) ?>">
                    <i>
                        <?php $messengerCode = $link['code']; include __DIR__ . '/messenger-icon.php'; ?>
                    </i>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
