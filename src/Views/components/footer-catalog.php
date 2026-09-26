<?php

declare(strict_types=1);

/**
 * Футер для страниц каталога — вариант из макета our-products.html
 * (плоский кремовый фон с жёлтыми фигурами, карточка "Часы работы"),
 * отличается от футера главной страницы (footer.two — index.html,
 * components/footer.php), т.к. в исходном макете Patte у этих двух
 * шаблонов разные футеры. Часы работы 10:00–20:00 ежедневно — реальные
 * данные графика магазина (database.md, specialists.work_start/work_end).
 */
?>
<footer style="background-color: #fff8e5; background-image: url(/assets/img/banners/banner-catalog.png)">
    <div class="container">
        <div class="row">
            <div class="col-xl-4 col-lg-6">
                <div class="logo">
                    <a href="/">
                        <img src="/assets/img/logo.png" alt="PetPark — логотип">
                    </a>
                    <p>
                        Зоомагазин и центр ухода за питомцами в Ростове-на-Дону:
                        каталог товаров, груминг и ветконсультации для кошек,
                        собак и декоративных птиц.
                    </p>
                    <div class="phone">
                        <i>
                            <svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 512 512" style="enable-background: new 0 0 512 512" xml:space="preserve">
                                <path d="M0,81v350h512V81H0z M456.952,111L256,286.104L55.047,111H456.952z M30,128.967l134.031,116.789L30,379.787V128.967z
                    M51.213,401l135.489-135.489L256,325.896l69.298-60.384L460.787,401H51.213z M482,379.788L347.969,245.756L482,128.967V379.788z"></path>
                            </svg>
                        </i>
                        <a href="mailto:info@petpark.ru">info@petpark.ru</a>
                    </div>
                    <div class="phone mb-0 d-flax align-items-center">
                        <i>
                            <svg version="1.1" xml:space="preserve" width="682.66669" height="682.66669" viewBox="0 0 682.66669 682.66669" xmlns="http://www.w3.org/2000/svg">
                                <clipPath clipPathUnits="userSpaceOnUse">
                                    <path d="M 0,512 H 512 V 0 H 0 Z"></path>
                                </clipPath>
                                <g transform="matrix(1.3333333,0,0,-1.3333333,0,682.66667)">
                                    <g>
                                        <g clip-path="url(#clipPath2333)">
                                            <g transform="translate(256,92)">
                                                <path d="m 0,0 c -126.964,143.662 -160,165.23 -160,240 0,88.366 71.634,160 160,160 88.365,0 160,-71.634 160,-160 C 160,165.854 130.212,147.337 0,0 Z"
                                                    style="fill: none; stroke: #000; stroke-width: 40; stroke-linecap: square; stroke-linejoin: miter; stroke-miterlimit: 10; stroke-dasharray: none; stroke-opacity: 1;"></path>
                                            </g>
                                            <g transform="translate(316,372)">
                                                <path d="m 0,0 -80,-80 -40,40"
                                                    style="fill: none; stroke: #000; stroke-width: 40; stroke-linecap: square; stroke-linejoin: miter; stroke-miterlimit: 10; stroke-dasharray: none; stroke-opacity: 1;"></path>
                                            </g>
                                        </g>
                                    </g>
                                </g>
                            </svg>
                        </i>
                        <p>Ростов-на-Дону</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-lg-6">
                <div class="widget-title">
                    <h3>Категории</h3>
                    <div class="boder"></div>
                    <ul>
                        <li>
                            <i class="fa-solid fa-angle-right"></i>
                            <a href="#">Товары для кошек</a>
                        </li>
                        <li>
                            <i class="fa-solid fa-angle-right"></i>
                            <a href="#">Товары для собак</a>
                        </li>
                        <li>
                            <i class="fa-solid fa-angle-right"></i>
                            <a href="#">Товары для птиц</a>
                        </li>
                        <li>
                            <i class="fa-solid fa-angle-right"></i>
                            <a href="#">Груминг</a>
                        </li>
                        <li>
                            <i class="fa-solid fa-angle-right"></i>
                            <a href="#">Ветконсультации</a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="col-xl-4 col-lg-6">
                <div class="working-hours">
                    <div class="widget-title">
                        <h3>Часы работы</h3>
                        <div class="boder"></div>
                        <div class="working-time">
                            <h6 class="pt-0">Ежедневно <span>10:00 – 20:00</span></h6>
                            <div class="call-us">
                                <img src="/assets/img/hadphon.png" alt="">
                                <div>
                                    <a href="tel:+78000000000">+7 (800) 000-00-00</a>
                                    <span>Есть вопросы? Звоните</span>
                                </div>
                            </div>
                            <?php include __DIR__ . '/social-icons.php'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="copyright">
            <p>PetPark — Copyright 2026</p>
        </div>
    </div>
    <img src="/assets/img/banners/hero-shaps-1.png" alt="" class="img-2">
    <img src="/assets/img/banners/dabal-foot-1.png" alt="" class="img-3">
    <img src="/assets/img/banners/hero-shaps-1.png" alt="" class="img-4">
</footer>
