<?php

declare(strict_types=1);

/**
 * Личный кабинет Покупателя — заглушка-приёмник после входа
 * (phase-1.md: содержимое — Фаза 7).
 */

$pageTitle = seoTitle('generic');
$pageDescription = seoDescription('generic');
$footerVariant = 'catalog';

ob_start();
?>
<section class="gap">
    <div class="container">
        <h1>Личный кабинет</h1>
        <p>Вы вошли в личный кабинет. Заказы, записи и избранное появятся здесь позже.</p>
        <form method="post" action="/logout">
            <?= csrfField() ?>
            <button type="submit" class="button">Выйти</button>
        </form>
    </div>
</section>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/public.php';
