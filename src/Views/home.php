<?php

declare(strict_types=1);

/**
 * Главная — SCR-01 (phase-1.md, Таск 5): слайдер (статичный — реальных
 * баннеров из БД/админки нет до Фазы 7), «Груминг и ветконсультации»
 * (карточки Услуг), «О компании», блок FAQ (аккордеон, статичные
 * вопросы/ответы — реального справочника вопросов в БД нет), опубли-
 * кованные отзывы, промо-баннер каталога (`.mockup`, статичный, ведёт
 * на /catalog), форма подписки над футером (`.subscribe` — визуал по
 * макету, без реальной отправки: подписки на рассылку в проекте нет,
 * вне scope Таска 5). Отдельный блок «Услуги» с теми же двумя карточками
 * убран — дублировал «Груминг и ветконсультации» выше на этой же
 * странице (по просьбе пользователя). Меню Категорий в шапке строит
 * сам components/header.php.
 * @var array<int, array<string, mixed>> $reviews          reviewPublishedForHome()
 * @var array{average: float, count: int} $reviewsAggregate reviewPublishedAggregate() — по ВСЕМ опубликованным отзывам, не только по показанным в карусели
 * @var array<int, array<string, mixed>> $rootCategories   catalogBuildCategoryTree(categoryAll()) — только корневые категории, как в шапке
 * @var array<int, array{category: array<string, mixed>, products: array<int, array<string, mixed>>}> $categoryTabs
 *      по корневой Категории (с непустым списком Товаров) — форма строки products та же, что у components/product-card.php
 */

$pageTitle = seoTitle('generic');
$pageDescription = seoDescription('generic');

/**
 * Иконка корневой Категории — общая для блока «Мы знаем...» и вкладок
 * «Популярные товары», вынесена в функцию, чтобы не дублировать один и
 * тот же match в двух местах этого файла.
 */
function homeCategoryIcon(string $slug): string
{
    return match ($slug) {
        'korma' => 'pets-icon-2.png',
        'lakomstva' => 'pets-icon-6.png',
        'aksessuary' => 'pets-icon-3.png',
        'igrushki' => 'pets-icon-4.png',
        'sredstva-gigieny' => 'pets-icon-1.png',
        default => 'pets-icon-1.png',
    };
}

ob_start();
?>
<section class="hero-two">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-6">
        <div class="hero-two-img">
          <div class="hero-two-slider owl-carousel owl-theme">
            <div class="item">
              <img src="/assets/img/home-page/slide-1.png" alt="Ветеринар с щенком на руках">
            </div>
            <div class="item">
              <img src="/assets/img/home-page/slide-image-2.png" alt="Мужчина обнимается с чихуахуа">
            </div>
            <div class="item">
              <img src="/assets/img/home-page/slide-image-3.png" alt="Девушка держит двух щенков шиба-ину">
            </div>
            <div class="item">
              <img src="/assets/img/home-page/slide-4.png" alt="Девушка со шпицем на руках">
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="hero-two-text">
          <h1>Забота о питомцах, которая чувствуется</h1>
          <p>
            Корма, аксессуары и уход для кошек, собак и декоративных птиц —
            с доставкой и самовывозом в Ростове-на-Дону.
          </p>
          <div class="hero-two-cta">
            <a href="/catalog" class="button">Смотреть каталог</a>
          </div>
        </div>
      </div>
    </div>
  </div>
  <img src="/assets/img/home-page/hero-shaps-2.png" class="hero-shaps-2" alt="">
  <img src="/assets/img/home-page/hero-shaps-3.png" class="hero-shaps-3" alt="">
  <img src="/assets/img/home-page/hero-shaps-3.png" class="hero-shaps-4" alt="">
</section>

<section class="gap">
  <div class="container">
    <div class="heading two">
      <h2>Мы знаем, как сильно вы любите своих питомцев. Мы тоже</h2>
    </div>
    <div class="row align-items-center">
      <div class="col-lg-6">
        <div class="love-your-pets">
          <p>
            PetPark — это забота о питомцах в мелочах: от выбора
            корма до подбора игрушки, которая понравится именно
            вашему любимцу.
          </p>
          <ul class="list">
            <li>
              <img src="/assets/img/list.png" alt="">
              Проверенные бренды кормов и товаров
            </li>
            <li>
              <img src="/assets/img/list.png" alt="">
              Поможем подобрать корм под возраст и породу
            </li>
            <li>
              <img src="/assets/img/list.png" alt="">
              Консультанты — сами держат питомцев
            </li>
            <li>
              <img src="/assets/img/list.png" alt="">
              Забота на каждом этапе покупки
            </li>
          </ul>
          <div class="company-oner">
            <img src="/assets/img/home-page/girl.jpg" alt="Виктория Смирнова">
            <svg width="116" height="116" viewBox="0 0 673 673" xmlns="http://www.w3.org/2000/svg">
              <path fill-rule="evenodd" clip-rule="evenodd"
                d="M9.82698 416.603C-19.0352 298.701 18.5108 173.372 107.497 90.7633L110.607 96.5197C24.3117 177.199 -12.311 298.935 15.0502 413.781L9.82698 416.603ZM89.893 565.433C172.674 654.828 298.511 692.463 416.766 663.224L414.077 658.245C298.613 686.363 175.954 649.666 94.9055 562.725L89.893 565.433ZM656.842 259.141C685.039 374.21 648.825 496.492 562.625 577.656L565.413 582.817C654.501 499.935 691.9 374.187 662.536 256.065L656.842 259.141ZM581.945 107.518C499.236 18.8371 373.997 -18.4724 256.228 10.5134L259.436 16.4515C373.888 -10.991 495.248 25.1518 576.04 110.708L581.945 107.518Z"
                fill="#000"></path>
            </svg>
            <div>
              <h3>Виктория Смирнова</h3>
              <p>Основатель PetPark</p>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="pets-icon-text home-pets-icon-text">
          <?php foreach ($rootCategories as $rootCategory): ?>
          <div class="pets-icon">
            <img src="/assets/img/<?= e(homeCategoryIcon((string) $rootCategory['slug'])) ?>" alt="">
            <a href="/catalog/<?= e((string) $rootCategory['slug']) ?>"><?= e((string) $rootCategory['name']) ?></a>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="gap no-top">
  <div class="container">
    <div class="heading">
      <img src="/assets/img/heading-img.png" alt="">
      <h6>Забота, которую видно</h6>
      <h2>Груминг и ветконсультации</h2>
    </div>
    <div class="row home-packages">
      <div class="col-lg-6 col-md-6">
        <div class="package">
          <figure>
            <img src="/assets/img/home-page/package-1.jpg" alt="Мытьё собаки на груминге">
          </figure>
          <div class="package-text">
            <i><img src="/assets/img/package-1.png" alt=""></i>
            <h4>Стоимость <span>по запросу</span></h4>
            <h3>Груминг</h3>
            <ul class="list">
              <li><i class="fa-solid fa-check"></i>Стрижка, мытьё, чистка когтей</li>
              <li><i class="fa-solid fa-check"></i>Опытная дружелюбная команда</li>
              <li><i class="fa-solid fa-check"></i>Уход, за который не стыдно</li>
              <li><i class="fa-solid fa-check"></i>Запись через сайт — скоро</li>
            </ul>
          </div>
        </div>
      </div>
      <div class="col-lg-6 col-md-6">
        <div class="package mb-0">
          <figure>
            <img src="/assets/img/home-page/package-2.jpg" alt="Внимательное общение с питомцем">
          </figure>
          <div class="package-text">
            <i><img src="/assets/img/package-2.png" alt=""></i>
            <h4>Стоимость <span>по запросу</span></h4>
            <h3>Ветконсультации</h3>
            <ul class="list">
              <li><i class="fa-solid fa-check"></i>Консультация по здоровью питомца</li>
              <li><i class="fa-solid fa-check"></i>Подбор корма и добавок</li>
              <li><i class="fa-solid fa-check"></i>Внимательное отношение к питомцу</li>
              <li><i class="fa-solid fa-check"></i>Запись через сайт — скоро</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="gap section-healthy-product home-visit-section">
  <div class="container">
    <div class="home-visit">
      <div>
        <h2><span>Скидка 50%</span> на первое посещение</h2>
        <p>Уход и забота о вашем питомце — груминг и ветконсультации от опытной команды</p>
        <a href="/services" class="button">Записаться</a>
      </div>
      <img src="/assets/img/home-page/home-visit.png" alt="Мужчина держит на руках собаку породы джек-рассел-терьер">
    </div>

    <?php if ($categoryTabs !== []): ?>
    <div class="gap no-bottom">
      <div class="row">
        <div class="col-lg-6">
          <div class="heading two">
            <h6>Подберите то, что нужно</h6>
            <h2>Популярные товары</h2>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="nav nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
            <?php foreach ($categoryTabs as $index => $categoryTab): ?>
            <?php $categorySlug = (string) $categoryTab['category']['slug']; ?>
            <button class="nav-link<?= $index === 0 ? ' active' : '' ?>" id="v-pills-<?= e($categorySlug) ?>-tab"
              data-bs-toggle="pill" data-bs-target="#v-pills-<?= e($categorySlug) ?>" type="button" role="tab"
              aria-controls="v-pills-<?= e($categorySlug) ?>" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>">
              <i><img src="/assets/img/<?= e(homeCategoryIcon($categorySlug)) ?>" alt=""></i>
              <?= e((string) $categoryTab['category']['name']) ?>
            </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="tab-content" id="v-pills-tabContent">
        <?php foreach ($categoryTabs as $index => $categoryTab): ?>
        <?php $categorySlug = (string) $categoryTab['category']['slug']; ?>
        <div class="tab-pane fade<?= $index === 0 ? ' show active' : '' ?>" id="v-pills-<?= e($categorySlug) ?>"
          role="tabpanel" aria-labelledby="v-pills-<?= e($categorySlug) ?>-tab">
          <div class="row">
            <?php foreach ($categoryTab['products'] as $product): ?>
            <?php include __DIR__ . '/components/product-card.php'; ?>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>

<section class="gap">
  <div class="container">
    <div class="heading two">
      <h6>Кто мы</h6>
      <h2>О компании</h2>
    </div>
    <div class="row align-items-center">
      <div class="col-lg-6">
        <div class="love-your-pets">
          <p>
            PetPark — зоомагазин и центр ухода за питомцами в
            Ростове-на-Дону. Помогаем владельцам кошек, собак и
            декоративных птиц заботиться о них: от корма и аксессуаров
            до груминга и ветконсультаций.
          </p>
          <ul class="list">
            <li>
              <img src="/assets/img/list.png" alt="">
              Доставка и самовывоз по Ростову-на-Дону
            </li>
            <li>
              <img src="/assets/img/list.png" alt="">
              Товары для кошек, собак и декоративных птиц
            </li>
            <li>
              <img src="/assets/img/list.png" alt="">
              Груминг и ветконсультации в одном месте
            </li>
            <li>
              <img src="/assets/img/list.png" alt="">
              Отзывы покупателей на каждом товаре
            </li>
          </ul>
        </div>
      </div>
      <div class="col-lg-6 home-about-img">
        <img src="/assets/img/home-page/dogs-1.png"
          alt="Коллаж из фото собаки, собаки и кошки — питомцы, о которых заботится PetPark">
      </div>
    </div>
  </div>
</section>

<section class="gap home-faq">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-6">
        <div class="heading two">
          <h6>Отвечаем на</h6>
          <h2>Часто задаваемые вопросы</h2>
        </div>
        <div class="accordion-item">
          <a href="#" class="heading">
            <span class="title">Как оформить заказ?</span>
            <span class="icon"></span>
          </a>
          <div class="content">
            <p>
              Выбираете товары в каталоге — доставка курьером
              или самовывоз по Ростову-на-Дону.
            </p>
          </div>
        </div>
        <div class="accordion-item">
          <a href="#" class="heading">
            <span class="title">Как записаться на груминг или ветконсультацию?</span>
            <span class="icon"></span>
          </a>
          <div class="content">
            <p>
              Онлайн-запись с выбором специалиста и времени
              скоро появится на сайте — пока запишитесь по
              телефону <a href="tel:+78000000000">+7 (800) 000-00-00</a>.
            </p>
          </div>
        </div>
        <div class="accordion-item">
          <a href="#" class="heading">
            <span class="title">Для каких питомцев у вас товары и услуги?</span>
            <span class="icon"></span>
          </a>
          <div class="content">
            <p>
              Для кошек, собак и декоративных птиц — от корма
              и аксессуаров до груминга и ветконсультаций.
            </p>
          </div>
        </div>
        <div class="accordion-item">
          <a href="#" class="heading">
            <span class="title">Поможете подобрать корм или уход для питомца?</span>
            <span class="icon"></span>
          </a>
          <div class="content">
            <p>
              Да, консультанты сами держат питомцев и помогут
              подобрать корм и уход под возраст и породу.
            </p>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="row">
          <div class="col-6">
            <div class="faq-img">
              <img src="/assets/img/home-page/faq-1.jpg" alt="Французский бульдог с лакомством">
              <img src="/assets/img/home-page/faq-3.jpg" alt="Девушка играет с кудрявой собакой дома">
              <img src="/assets/img/home-page/faq-5.jpg" alt="Груминг кошки — вычёсывание шерсти">
            </div>
          </div>
          <div class="col-6">
            <div class="faq-img two">
              <img src="/assets/img/home-page/faq-2.jpg" alt="Мужчина обнимается с золотистым ретривером">
              <img src="/assets/img/home-page/faq-4.jpg" alt="Девочка гладит лошадь">
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <img src="/assets/img/home-page/faq-shaps.png" class="faq-shaps" alt="">
</section>

<?php if ($reviews !== []): ?>
<section class="gap home-reviews">
  <div class="container">
    <div class="heading two">
      <h6>Нам доверяют</h6>
      <h2>Отзывы покупателей</h2>
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
              <a href="/product/<?= e((string) $review['product_slug']) ?>/">
                <?= e((string) $review['product_name']) ?>
              </a>
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

<section class="gap">
  <div class="container">
    <div class="mockup">
      <h3>Найдите всё нужное для питомца в нашем <span>каталоге</span></h3>
      <div class="mockup-img">
        <img src="/assets/img/home-page/mockup.png" alt="Собака смотрит вверх, ожидая лакомство">
      </div>
      <div class="mockup-text">
        <p>
          Корма, аксессуары и уход для кошек, собак и декоративных
          птиц — с доставкой и самовывозом по Ростову-на-Дону.
        </p>
        <a href="/catalog" class="button">Смотреть каталог</a>
      </div>
    </div>
  </div>
</section>

<section class="subscribe">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-6">
        <div class="subscribe-text">
          <img src="/assets/img/home-page/subscribe-icon.png" alt="">
          <div>
            <h2>Подпишитесь на новости</h2>
            <p>Узнавайте о новых товарах и предложениях первыми</p>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <?php // Визуал по макету — без реальной отправки: подписки на рассылку в проекте пока нет (не в scope Таска 5). ?>
        <form class="subscribe">
          <input type="email" name="email" placeholder="Введите e-mail">
          <button type="button" class="button">Подписаться</button>
        </form>
      </div>
    </div>
  </div>
</section>

<?php
$content = (string) ob_get_clean();

require __DIR__ . '/layouts/public.php';