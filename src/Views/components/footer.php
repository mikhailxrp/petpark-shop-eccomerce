<footer class="two" style="background-color: #f5f5f5; background-image: url(/assets/img/footer-1.png);">
    <div class="container">
        <div class="row">
            <div class="col-lg-4">
                <div class="logo">
                    <a href="/">
                        <img src="/assets/img/logo.png" alt="PetPark — логотип">
                    </a>
                    <p>
                        Зоомагазин и центр ухода за питомцами в Ростове-на-Дону:
                        каталог товаров, груминг и ветконсультации для кошек,
                        собак и декоративных птиц.
                    </p>
                </div>
                <?php include __DIR__ . '/social-icons.php'; ?>
            </div>
            <div class="col-lg-4">
                <div class="widget-title">
                    <h3>Категории</h3>
                    <div class="boder"></div>
                    <ul>

                        <?php foreach ($headerCategoryTree as $rootCategory): ?>

                            <li>

                                <i class="fa-solid fa-angle-right"></i>

                                <a href="/catalog/<?= e((string) $rootCategory['slug']) ?>"><?= e((string) $rootCategory['name']) ?></a>

                            </li>

                        <?php endforeach; ?>

                        <li>

                            <i class="fa-solid fa-angle-right"></i>

                            <a href="/booking">Груминг</a>

                        </li>

                        <li>

                            <i class="fa-solid fa-angle-right"></i>

                            <a href="/booking">Ветконсультации</a>

                        </li>

                    </ul>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="widget-title">
                    <h3>Свяжитесь с нами</h3>
                    <div class="boder"></div>
                    <div class="phone">
                        <i>
                            <svg height="112" viewBox="0 0 24 24" width="112" xmlns="http://www.w3.org/2000/svg">
                                <g clip-rule="evenodd" fill="rgb(255255,255)" fill-rule="evenodd">
                                    <path d="m7 2.75c-.41421 0-.75.33579-.75.75v17c0 .4142.33579.75.75.75h10c.4142 0 .75-.3358.75-.75v-17c0-.41421-.3358-.75-.75-.75zm-2.25.75c0-1.24264 1.00736-2.25 2.25-2.25h10c1.2426 0 2.25 1.00736 2.25 2.25v17c0 1.2426-1.0074 2.25-2.25 2.25h-10c-1.24264 0-2.25-1.0074-2.25-2.25z"></path>
                                    <path d="m10.25 5c0-.41421.3358-.75.75-.75h2c.4142 0 .75.33579.75.75s-.3358.75-.75.75h-2c-.4142 0-.75-.33579-.75-.75z"></path>
                                    <path d="m9.25 19c0-.4142.33579-.75.75-.75h4c.4142 0 .75.3358.75.75s-.3358.75-.75.75h-4c-.41421 0-.75-.3358-.75-.75z"></path>
                                </g>
                            </svg>
                        </i>
                        <a href="tel:<?= e(SHOP_PHONE_TEL) ?>"><?= e(SHOP_PHONE) ?></a>
                    </div>
                    <div class="phone">
                        <i>
                            <svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 512 512" style="enable-background: new 0 0 512 512" xml:space="preserve">
                                <path d="M0,81v350h512V81H0z M456.952,111L256,286.104L55.047,111H456.952z M30,128.967l134.031,116.789L30,379.787V128.967z
                    M51.213,401l135.489-135.489L256,325.896l69.298-60.384L460.787,401H51.213z M482,379.788L347.969,245.756L482,128.967V379.788z"></path>
                            </svg>
                        </i>
                        <a href="mailto:<?= e(SHOP_EMAIL) ?>"><?= e(SHOP_EMAIL) ?></a>
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
                        <div>
                            <p>Ростов-на-Дону</p>
                            <a href="#" class="get">Проложить маршрут</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="copyrighttwo">
        <div class="container">
            <div class="copyright">
                <p>PetPark — Copyright 2026</p>
                <?php include __DIR__ . '/footer-legal.php'; ?>
            </div>
        </div>
    </div>
</footer>
