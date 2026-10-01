<?php

declare(strict_types=1);

/**
 * Меню Личного кабинета — одно на все страницы кабинета.
 * @var string $accountActive Раздел: 'overview' | 'pets' | 'bookings'
 */

$accountLinks = [
    'overview' => ['url' => '/account', 'label' => 'Обзор', 'icon' => 'fa-user'],
    'pets'     => ['url' => '/account/pets', 'label' => 'Мои питомцы', 'icon' => 'fa-paw'],
    'bookings' => ['url' => '/account/bookings', 'label' => 'Мои записи', 'icon' => 'fa-calendar-check'],
];
?>
<nav class="account-nav" aria-label="Разделы кабинета">
    <ul class="account-nav__list">
        <?php foreach ($accountLinks as $key => $link): ?>
            <?php $isActive = $key === $accountActive; ?>
            <li>
                <a class="account-nav__link<?= $isActive ? ' account-nav__link--active' : '' ?>"
                   href="<?= e($link['url']) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                    <i class="fa-solid <?= e($link['icon']) ?>" aria-hidden="true"></i>
                    <?= e($link['label']) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <form class="account-nav__logout" method="post" action="/logout">
        <?= csrfField() ?>
        <button type="submit" class="account-nav__logout-button">
            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
            Выйти
        </button>
    </form>
</nav>
