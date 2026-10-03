<?php

declare(strict_types=1);

/**
 * Ссылки подвала на документы и «Контакты» (phase-8.md, Таск 2, FR-CNT-013) —
 * общие для двух вариантов подвала (footer.php, footer-catalog.php).
 */
$legalLinks = [
    ['name' => 'Политика обработки ПДн', 'url' => '/privacy'],
    ['name' => 'Публичная оферта', 'url' => '/offer'],
    ['name' => 'Контакты', 'url' => '/contacts'],
];
?>
<nav class="footer-legal" aria-label="Документы и контакты">
    <ul class="footer-legal__list">
        <?php foreach ($legalLinks as $legalLink): ?>
            <li class="footer-legal__item">
                <a class="footer-legal__link" href="<?= e($legalLink['url']) ?>"><?= e($legalLink['name']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
