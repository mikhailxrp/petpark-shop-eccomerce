<?php
declare(strict_types=1);

/** @var bool $isDev */
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ошибка сервера | PetPark</title>
</head>
<body>
    <main>
        <h1>Что-то пошло не так</h1>
        <?php if ($isDev): ?>
            <p>Подробности — в <code>storage/logs/app.log</code>.</p>
        <?php else: ?>
            <p>Мы уже знаем о проблеме. Попробуйте обновить страницу позже.</p>
        <?php endif; ?>
    </main>
</body>
</html>
