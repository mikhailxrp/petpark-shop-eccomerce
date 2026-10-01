<?php

declare(strict_types=1);

/**
 * Шапка — на каждой странице сайта (layouts/public.php), поэтому дерево
 * Категорий для выпадающего меню «Каталог» строит сама, а не получает
 * через Controller/layout (иначе пришлось бы передавать его из каждого
 * Controller, рендерящего через public.php — CatalogController,
 * ProductController, SearchController, HomeController).
 */
$headerCategoryTree = catalogBuildCategoryTree(categoryAll());

// Мини-корзина (Таск 3, FR-CART-007) — считаем сами, тем же принципом,
// что дерево Категорий выше: шапка сама запрашивает свои данные, чтобы
// каждый Controller, рендерящий через public.php, не прокидывал их.
$headerCartSummary = cartSummarize(cartItemsForOwner(cartOwner()));
$headerCartCount = 0;
foreach ($headerCartSummary['items'] as $headerCartItem) {
    $headerCartCount += (int) $headerCartItem['quantity'];
}
?>
<header class="two">
    <div class="top-bar">
        <div class="container">
            <div class="top-bar-slid">
                <div>
                    <div class="phone-data">
                        <div class="phone">
                            <i>
                                <svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 512 512" style="enable-background: new 0 0 512 512" xml:space="preserve">
                                    <path d="M0,81v350h512V81H0z M456.952,111L256,286.104L55.047,111H456.952z M30,128.967l134.031,116.789L30,379.787V128.967z
                    M51.213,401l135.489-135.489L256,325.896l69.298-60.384L460.787,401H51.213z M482,379.788L347.969,245.756L482,128.967V379.788z"></path>
                                </svg>
                            </i>
                            <a href="mailto:info@petpark.ru">info@petpark.ru</a>
                        </div>
                        <div class="phone d-flax align-items-center">
                            <i>
                                <svg height="112" viewBox="0 0 24 24" width="112" xmlns="http://www.w3.org/2000/svg">
                                    <g clip-rule="evenodd" fill="rgb(255255,255)" fill-rule="evenodd">
                                        <path d="m7 2.75c-.41421 0-.75.33579-.75.75v17c0 .4142.33579.75.75.75h10c.4142 0 .75-.3358.75-.75v-17c0-.41421-.3358-.75-.75-.75zm-2.25.75c0-1.24264 1.00736-2.25 2.25-2.25h10c1.2426 0 2.25 1.00736 2.25 2.25v17c0 1.2426-1.0074 2.25-2.25 2.25h-10c-1.24264 0-2.25-1.0074-2.25-2.25z"></path>
                                        <path d="m10.25 5c0-.41421.3358-.75.75-.75h2c.4142 0 .75.33579.75.75s-.3358.75-.75.75h-2c-.4142 0-.75-.33579-.75-.75z"></path>
                                        <path d="m9.25 19c0-.4142.33579-.75.75-.75h4c.4142 0 .75.3358.75.75s-.3358.75-.75.75h-4c-.41421 0-.75-.3358-.75-.75z"></path>
                                    </g>
                                </svg>
                            </i>
                            <a class="me-3" href="tel:+78000000000">+7 (800) 000-00-00</a>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="time">
                        <div class="ordering">
                            <a href="#">Доставка</a>
                            <div class="line"></div>
                            <a href="#">Оплата</a>
                            <div class="line"></div>
                            <a href="#">Возврат</a>
                        </div>
                        <div class="login">
                            <i class="fa-solid fa-user"></i>
                            <a href="/login">Вход</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="container">
        <div class="bottom-bar">
            <a href="/"><img src="/assets/img/logo.png" alt="PetPark — логотип"></a>
            <nav class="navbar">
                <ul class="navbar-links">
                    <li class="navbar-dropdown">
                        <a href="/">
                            <i><img alt="Главная" src="/assets/img/home.png"></i>
                            Главная
                        </a>
                    </li>
                    <li class="navbar-dropdown">
                        <a href="#">О компании</a>
                    </li>
                    <li class="navbar-dropdown menu-item-children">
                        <a href="/booking">
                            <i></i>
                            Услуги
                        </a>
                        <div class="dropdown">
                            <a href="#">Груминг</a>
                            <a href="#">Ветконсультации</a>
                            <a href="/booking">Записаться</a>
                        </div>
                    </li>
                    <li class="navbar-dropdown menu-item-children">
                        <a href="/catalog">Каталог</a>
                        <div class="dropdown">
                            <?php foreach ($headerCategoryTree as $rootCategory): ?>
                                <a href="/catalog/<?= e((string) $rootCategory['slug']) ?>"><?= e((string) $rootCategory['name']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </li>
                    <li class="navbar-dropdown">
                        <a href="#">Контакты</a>
                    </li>
                </ul>
            </nav>
            <div class="menu-end">
                <div class="bar-menu">
                    <i class="fa-solid fa-bars"></i>
                </div>
                <div class="header-search-button search-box-outer">
                    <a href="javascript:void(0)" class="search-btn">
                        <svg height="512" viewBox="0 0 24 24" width="512" xmlns="http://www.w3.org/2000/svg">
                            <g id="_12" data-name="12">
                                <path d="m21.71 20.29-2.83-2.82a9.52 9.52 0 1 0 -1.41 1.41l2.82 2.83a1 1 0 0 0 1.42 0 1 1 0 0 0 0-1.42zm-17.71-8.79a7.5 7.5 0 1 1 7.5 7.5 7.5 7.5 0 0 1 -7.5-7.5z"></path>
                            </g>
                        </svg>
                    </a>
                </div>
                <div class="line"></div>
                <a href="#"><i class="fa-regular fa-heart"></i></a>
                <div class="line"></div>
                <div class="cart-widget">
                    <a
                        href="/cart"
                        class="cart-widget__toggle"
                        id="cart-widget-toggle"
                        aria-expanded="false"
                        aria-controls="cart-widget-popup"
                        aria-label="Корзина"
                    >
                        <i class="fa-solid fa-cart-shopping"></i>
                        <span class="cart-widget__count<?= $headerCartCount === 0 ? ' d-none' : '' ?>" id="cart-count"><?= $headerCartCount ?></span>
                    </a>
                    <div class="cart-popup" id="cart-widget-popup" aria-labelledby="cart-widget-toggle">
                        <ul class="cart-popup__list" id="cart-popup-items">
                            <?php foreach ($headerCartSummary['items'] as $headerCartItem): ?>
                                <li class="cart-popup__item" data-item-id="<?= (int) $headerCartItem['id'] ?>">
                                    <img class="cart-popup__item-img" src="/assets/img/food-1.png" alt="<?= e((string) $headerCartItem['name']) ?>" width="50" height="50">
                                    <div class="cart-popup__item-info">
                                        <p class="cart-popup__item-name"><?= e((string) $headerCartItem['name']) ?></p>
                                        <p class="cart-popup__item-line cart-popup-item__line">
                                            <?= (int) $headerCartItem['quantity'] ?> шт. — <?= e(cartFormatMoney($headerCartItem['line_total'])) ?> ₽
                                        </p>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                            <li id="cart-popup-empty" class="cart-popup__empty<?= $headerCartCount > 0 ? ' d-none' : '' ?>">Корзина пуста</li>
                        </ul>
                        <div class="cart-popup__total">
                            <span>Итого:</span>
                            <span id="cart-popup-subtotal"><?= e(cartFormatMoney($headerCartSummary['subtotal'])) ?> ₽</span>
                        </div>
                        <a class="cart-popup__cta" href="/cart">Перейти в корзину</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="mobile-nav hmburger-menu" id="mobile-nav" style="display: block">
        <div class="res-log">
            <a href="/">
                <img src="/assets/img/logo-w.png" alt="PetPark — логотип">
            </a>
        </div>
        <ul>
            <li><a href="/">Главная</a></li>
            <li><a href="#">О компании</a></li>
            <li class="menu-item-has-children">
                <a href="JavaScript:void(0)">Услуги</a>
                <ul class="sub-menu">
                    <li><a href="#">Груминг</a></li>
                    <li><a href="#">Ветконсультации</a></li>
                    <li><a href="/booking">Записаться</a></li>
                </ul>
            </li>
            <li class="menu-item-has-children">
                <a href="/catalog">Каталог</a>
                <ul class="sub-menu">
                    <?php foreach ($headerCategoryTree as $rootCategory): ?>
                        <li><a href="/catalog/<?= e((string) $rootCategory['slug']) ?>"><?= e((string) $rootCategory['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </li>
            <li><a href="#">Контакты</a></li>
        </ul>
        <?php include __DIR__ . '/social-icons.php'; ?>
        <a href="JavaScript:void(0)" id="res-cross"></a>
    </div>
</header>
<div class="search-popup">
    <button class="close-search style-two" aria-label="Закрыть поиск">
        <span class="flaticon-multiply">
            <i class="far fa-times-circle"></i>
        </span>
    </button>
    <button class="close-search" aria-label="Закрыть поиск">
        <i class="fa-solid fa-arrow-right"></i>
    </button>
    <form method="get" action="/search">
        <div class="form-group">
            <input
                type="search"
                name="q"
                value=""
                placeholder="Поиск товаров..."
                minlength="2"
                required
            >
            <button type="submit"><i class="fa fa-search"></i></button>
        </div>
    </form>
</div>
