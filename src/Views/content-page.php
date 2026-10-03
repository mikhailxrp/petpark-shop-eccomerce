<?php

declare(strict_types=1);

/**
 * Страница «О компании» — /about (phase-8.md, Таск 1), макет `about.html`
 * плюс блоки «История» (`history.html`) и «Как мы работаем»
 * (`how-we-works.html`). Заголовок — `content_pages.title`, вступление
 * «Welcome» — `body` (уже очищено contentHtmlSanitize()); разметка и
 * тексты остальных блоков — здесь (planning-log.md, сужение ADR-008).
 * Видео-ролик макета заменён постером без ссылки (без внешних запросов),
 * логотипы партнёров и награды макета не выводятся — данных о них нет.
 * @var array<string, mixed>                $page
 * @var string                              $bodyHtml        очищенный `body`
 * @var array<int, array<string, mixed>>    $reviews         reviewPublishedForHome()
 * @var array{average: float, count: int}   $reviewsAggregate reviewPublishedAggregate()
 * @var array<int, array{name: string, position: string, photo: string}> $team
 * @var array<int, array{id: int, path: string, sort_order: int}> $galleryImages
 * @var array<int, array{number: int, suffix: string, label: string, icon: string}> $stats
 * @var array<int, array{year: string, title: string, text: string, modifier: string}> $timeline
 * @var array<int, array{number: int, title: string, text: string, icon: string, modifier: string}> $steps
 */

$breadcrumbs = [
    ['name' => 'Главная', 'url' => '/'],
    ['name' => (string) $page['title'], 'url' => null],
];
$bannerTitle = (string) $page['title'];

$weProvide = [
    ['title' => 'Груминг', 'text' => 'Мытьё, стрижка и тримминг для собак и кошек у опытных мастеров.', 'href' => '/booking', 'image' => '/assets/img/home-page/package-1.jpg', 'alt' => 'Собака с мыльной пеной на голове во время мытья', 'ring' => '#fedc4f'],
    ['title' => 'Ветконсультации', 'text' => 'Приём ветеринарного врача, вакцинация и советы по уходу за питомцем.', 'href' => '/booking', 'image' => '/assets/img/home-page/package-2.jpg', 'alt' => 'Девушка играет с коричневым пуделем на диване', 'ring' => '#fb5e3c'],
    ['title' => 'Товары для питомцев', 'text' => 'Корма, лакомства, игрушки и аксессуары с доставкой и самовывозом.', 'href' => '/catalog', 'image' => '/assets/img/home-page/package-3.jpg', 'alt' => 'Далматин и другие собаки на прогулке с выгульщиками', 'ring' => '#fedc4f'],
];

$careServices = [
    ['title' => 'Онлайн-заказ', 'text' => 'Выберите товары в каталоге и оформите заказ за несколько минут.', 'href' => '/catalog', 'icon' => '/assets/img/welcome-to-3.png'],
    ['title' => 'Груминг', 'text' => 'Запишитесь к мастеру на удобное время прямо на сайте.', 'href' => '/booking', 'icon' => '/assets/img/welcome-to-1.png'],
    ['title' => 'Ветконсультации', 'text' => 'Ветеринарный врач осмотрит питомца и подскажет, как о нём заботиться.', 'href' => '/booking', 'icon' => '/assets/img/welcome-to-4.png'],
    ['title' => 'Доставка и самовывоз', 'text' => 'Привезём заказ по Ростову-на-Дону или подготовим к самовывозу.', 'href' => '/catalog', 'icon' => '/assets/img/welcome-to-2.png'],
];

$howImages = [
    ['src' => '/assets/img/home-page/package-1.jpg', 'alt' => 'Собака с мыльной пеной на голове во время мытья'],
    ['src' => '/assets/img/home-page/package-2.jpg', 'alt' => 'Девушка играет с коричневым пуделем на диване'],
    ['src' => '/assets/img/home-page/package-3.jpg', 'alt' => 'Далматин и другие собаки на прогулке с выгульщиками'],
];

// Раскладка галереи макета: колонки по 2 / 3 / 2 фото; «высокое» фото — первое в 1-й и последнее в 3-й колонке.
$galleryColumns = [[0, 1], [2, 3, 4], [5, 6]];
$galleryTall = [0, 6];

ob_start();
?>
<?php include __DIR__ . '/components/page-banner.php'; ?>

<section class="gap about">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="heading two">
                    <h2>Добро пожаловать в PetPark</h2>
                </div>
                <div class="love-your-pets">
                    <div class="about__intro"><?= $bodyHtml ?></div>
                    <ul class="list">
                        <li><img src="/assets/img/list.png" alt="">Кошки, собаки и декоративные птицы</li>
                        <li><img src="/assets/img/list.png" alt="">Груминг и ветконсультации в одном месте</li>
                        <li><img src="/assets/img/list.png" alt="">Консультанты — сами держат питомцев</li>
                        <li><img src="/assets/img/list.png" alt="">Доставка и самовывоз по Ростову-на-Дону</li>
                    </ul>
                    <div class="company-oner position-relative">
                        <img src="/assets/img/home-page/girl.jpg" alt="Виктория Смирнова">
                        <?php $ringSize = 116; $ringFill = '#000'; include __DIR__ . '/components/ring-svg.php'; ?>
                        <div>
                            <h3>Виктория Смирнова</h3>
                            <p>Основатель PetPark</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="dogs-img">
                    <img src="/assets/img/home-page/dogs-1.png" alt="Коллаж из фото собаки, собаки и кошки — питомцы, о которых заботится PetPark">
                </div>
            </div>
        </div>
    </div>
</section>

<section class="gap no-top">
    <div class="container">
        <div class="row">
            <?php foreach ($weProvide as $provide): ?>
                <?php include __DIR__ . '/components/we-provide.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="gap care-services about-care-services">
    <div class="container">
        <div class="heading">
            <img src="/assets/img/heading-img.png" alt="">
            <h6>Что мы предлагаем</h6>
            <h2>Забота о питомцах</h2>
        </div>
        <div class="row">
            <?php foreach ($careServices as $service): ?>
                <?php include __DIR__ . '/components/care-service.php'; ?>
            <?php endforeach; ?>
        </div>
        <div class="row mt-3">
            <div class="col-lg-6 col-md-6">
                <div class="video position-relative">
                    <figure>
                        <img src="/assets/img/home-page/faq-2.jpg" alt="Мужчина обнимает золотистого ретривера">
                    </figure>
                </div>
            </div>
            <div class="col-lg-6 col-md-6">
                <div class="video position-relative">
                    <figure>
                        <img src="/assets/img/home-page/faq-5.jpg" alt="Серая кошка во время расчёсывания">
                    </figure>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($team !== []): ?>
<section class="gap no-bottom">
    <div class="container">
        <div class="heading">
            <img src="/assets/img/heading-img.png" alt="">
            <h6>Наши специалисты</h6>
            <h2>Команда PetPark</h2>
        </div>
        <div class="row">
            <?php foreach ($team as $member): ?>
                <?php include __DIR__ . '/components/team-member.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="gap">
    <div class="container">
        <div class="row">
            <?php foreach ($stats as $stat): ?>
                <?php include __DIR__ . '/components/count-text.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($reviews !== []): ?>
<section class="gap section-client about-reviews">
    <div class="container">
        <div class="heading two">
            <h2>Что говорят наши клиенты</h2>
        </div>
        <div class="client-slider owl-carousel owl-theme">
            <?php foreach ($reviews as $review): ?>
            <div class="item">
                <div class="client">
                    <img src="/assets/img/client.png" alt="">
                    <div class="client-text">
                        <ul class="star">
                            <?php for ($i = 0; $i < 5; $i++): ?>
                            <li><i class="<?= $i < (int) $review['rating'] ? 'fa-solid' : 'fa-regular' ?> fa-star"></i></li>
                            <?php endfor; ?>
                        </ul>
                        <p><?= e((string) $review['body']) ?></p>
                        <h4><?= e((string) $review['author_name']) ?></h4>
                        <span>
                            <a href="/product/<?= e((string) $review['product_slug']) ?>/"><?= e((string) $review['product_name']) ?></a>
                        </span>
                        <i class="quote">
                            <img src="/assets/img/quote.png" alt="">
                        </i>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="rated">
            <ul class="star">
                <?php for ($i = 0; $i < 5; $i++): ?>
                <li><i class="<?= $i < (int) round($reviewsAggregate['average']) ? 'fa-solid' : 'fa-regular' ?> fa-star"></i></li>
                <?php endfor; ?>
            </ul>
            <h4>Рейтинг <?= e(number_format($reviewsAggregate['average'], 1)) ?> из 5.0</h4>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($galleryImages !== []): ?>
<section class="gap">
    <div class="container">
        <div class="heading">
            <img src="/assets/img/heading-img.png" alt="">
            <h6>Фотогалерея</h6>
            <h2>Наши воспоминания</h2>
        </div>
        <div class="row">
            <?php foreach ($galleryColumns as $indexes): ?>
                <?php $columnImages = array_intersect_key($galleryImages, array_flip($indexes)); ?>
                <?php if ($columnImages === []): ?>
                    <?php continue; ?>
                <?php endif; ?>
                <div class="col-lg-4 col-md-6">
                    <?php foreach ($columnImages as $index => $image): ?>
                        <?php $imageUrl = '/uploads/' . ltrim((string) $image['path'], '/'); ?>
                        <div class="about-gallery-img<?= in_array($index, $galleryTall, true) ? ' about-gallery-img--tall' : '' ?>">
                            <a href="<?= e($imageUrl) ?>" data-fancybox="gallery" aria-label="Открыть фото <?= $index + 1 ?> в галерее">
                                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                            </a>
                            <figure><img alt="Фото из галереи PetPark, <?= $index + 1 ?>" src="<?= e($imageUrl) ?>"></figure>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="gap about-history" id="history">
    <div class="container">
        <div class="row">
            <div class="col-lg-6">
                <div class="heading-history">
                    <h2>Краткая история PetPark <span>Мы начали в 2018 году</span></h2>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="heading-history">
                    <p>PetPark вырос из небольшого зоомагазина в Ростове-на-Дону в центр заботы о питомцах: каталог с доставкой, груминг и ветеринарные консультации — всё в одном месте.</p>
                </div>
                <div class="position-relative company-oner">
                    <img src="/assets/img/home-page/girl.jpg" alt="Виктория Смирнова">
                    <div>
                        <h3>Виктория Смирнова</h3>
                        <p>Основатель PetPark</p>
                    </div>
                </div>
            </div>
        </div>
        <h3 class="history">С чего всё начиналось</h3>
        <?php $lastIndex = array_key_last($timeline); ?>
        <?php foreach ($timeline as $index => $item): ?>
            <?php $isLast = $index === $lastIndex; ?>
            <?php include __DIR__ . '/components/history-item.php'; ?>
        <?php endforeach; ?>
    </div>
</section>

<section class="gap about-how-we-work" id="how-we-work">
    <div class="container">
        <div class="heading works">
            <h2>Как мы работаем</h2>
            <p>От выбора до получения — три простых шага, чтобы вашему питомцу было хорошо.</p>
        </div>
        <div class="row align-items-center">
            <?php $step = $steps[0]; include __DIR__ . '/components/works-step.php'; ?>
            <div class="col-lg-6">
                <div class="how-img">
                    <img src="<?= e($howImages[0]['src']) ?>" alt="<?= e($howImages[0]['alt']) ?>">
                </div>
            </div>
            <div class="col-lg-6">
                <div class="how-img two">
                    <img src="<?= e($howImages[1]['src']) ?>" alt="<?= e($howImages[1]['alt']) ?>">
                </div>
            </div>
            <?php $step = $steps[1]; include __DIR__ . '/components/works-step.php'; ?>
            <?php $step = $steps[2]; include __DIR__ . '/components/works-step.php'; ?>
            <div class="col-lg-6">
                <div class="how-img">
                    <img src="<?= e($howImages[2]['src']) ?>" alt="<?= e($howImages[2]['alt']) ?>">
                </div>
            </div>
        </div>
    </div>
</section>

<section class="gap">
    <div class="container">
        <div class="mockup">
            <h3>Запишите питомца на <span>груминг</span> или ветконсультацию</h3>
            <div class="mockup-img">
                <img src="/assets/img/home-page/mockup.png" alt="Собака смотрит вверх, ожидая лакомство">
            </div>
            <div class="mockup-text">
                <p>Выберите услугу, специалиста и удобное время — запись занимает пару минут.</p>
                <a href="/booking" class="button">Записаться</a>
            </div>
        </div>
    </div>
</section>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/layouts/public.php';
