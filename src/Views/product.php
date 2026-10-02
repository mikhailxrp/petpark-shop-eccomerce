<?php

declare(strict_types=1);

/**
 * Карточка товара — /product/{slug}/ (phase-1.md, Таск 4). Переключение
 * Вариантов — public/assets/js/product-variants.js по данным, уже
 * посчитанным здесь теми же функциями Core/Catalog.php, что и SSR —
 * никакой бизнес-логики цены/наличия во фронтенде. Отзывы (FR-CARD-005)
 * и «В избранное» (FR-CARD-006) — Таск 8, здесь не выводятся.
 * @var array<string, mixed>              $product         productFindBySlug() — id, category_id, name, slug, description, seo_title, seo_description
 * @var array<int, string>                $imagePaths      productImagePaths() — главное фото первым (пусто — заглушка)
 * @var array<int, array<string, mixed>>  $variants        productVariantsForProduct() + attributes (список attr_value по Варианту)
 * @var array<string, mixed>              $selectedVariant catalogSelectVariant()
 * @var array<int, array<string, mixed>>  $categoryChain   Главная-цепочка parent_id, без корня
 * @var array<int, array<string, mixed>>  $similarProducts productSimilarByCategory() — в пределах корневой Категории (ProductController), та же форма строки, что у components/product-card.php
 * @var array<int, array<string, mixed>>  $reviews         reviewsPublishedForProduct() — только published, для этого Товара
 * @var string                            $reviewFormToken generateFormToken('add-review') — антибот, скрытое поле формы отзыва
 * @var string|null                       $reviewError     flash 'review_error' — невалидная форма (не бот-сценарий)
 * @var string|null                       $reviewSuccess   flash 'review_success' — реальный успех И бот-сценарий выглядят одинаково (dod-global.md)
 * @var bool                              $isFavorite      favoriteExists() для текущего выбранного Варианта и авторизованного Покупателя
 * @var string|null                       $favoriteNotice  flash 'favorite_notice' — результат последнего переключения «В избранное»
 */

// Длинное название уменьшает шрифт <h1>, чтобы баннер не растягивался
const BANNER_TITLE_LONG_LENGTH = 30;
const BANNER_TITLE_XLONG_LENGTH = 70;

$bannerTitleLength = mb_strlen((string) $product['name']);
$bannerTitleModifier = match (true) {
    $bannerTitleLength > BANNER_TITLE_XLONG_LENGTH => ' banner-title--xlong',
    $bannerTitleLength > BANNER_TITLE_LONG_LENGTH  => ' banner-title--long',
    default                                        => '',
};

$minPrice = min(array_map(
    static fn (array $variant): float => catalogEffectivePrice(
        (float) $variant['price'],
        $variant['discount_price'] !== null ? (float) $variant['discount_price'] : null
    ),
    $variants
));

// Каталожная цена в <title> — минимальная среди активных Вариантов
// (database.md, ADR-004), не обязательно цена выбранного Варианта.
$pageTitle = seoTitle('product', [
    'seo_title' => $product['seo_title'],
    'name'      => $product['name'],
    'price'     => $minPrice,
]);
$pageDescription = seoDescription('product', [
    'seo_description' => $product['seo_description'],
    'name'             => $product['name'],
]);

$productUrl = '/product/' . $product['slug'] . '/';
$footerVariant = 'catalog';

$breadcrumbs = [
    ['name' => 'Главная', 'url' => '/'],
    ['name' => 'Каталог', 'url' => '/catalog'],
];
$breadcrumbUrls = catalogBreadcrumbUrls(array_column($categoryChain, 'slug'));
foreach ($categoryChain as $index => $node) {
    $breadcrumbs[] = ['name' => $node['name'], 'url' => $breadcrumbUrls[$index]];
}
$breadcrumbs[] = ['name' => $product['name'], 'url' => null];
$breadcrumbSchema = renderBreadcrumbSchema($breadcrumbs);

// Данные для переключателя (JS) — цена/наличие/артикул для КАЖДОГО
// Варианта посчитаны один раз теми же Core/Catalog.php-функциями, что и
// SSR-разметка выбранного Варианта ниже; JS только подставляет готовые
// строки в DOM, не пересчитывает правила скидки/наличия сам.
$variantsForJs = [];
foreach ($variants as $variant) {
    $hasDiscount = $variant['discount_price'] !== null;
    $effectivePrice = catalogEffectivePrice(
        (float) $variant['price'],
        $hasDiscount ? (float) $variant['discount_price'] : null
    );
    $status = catalogAvailabilityStatus((int) $variant['stock_quantity'], (int) $variant['reserved_quantity']);
    $available = max(0, (int) $variant['stock_quantity'] - (int) $variant['reserved_quantity']);

    $variantsForJs[$variant['id']] = [
        'priceHtml' => $hasDiscount
            ? sprintf(
                '<del>%s ₽</del><ins>%s ₽</ins>',
                e(seoFormatPrice($variant['price'])),
                e(seoFormatPrice($effectivePrice))
            )
            : sprintf('%s ₽', e(seoFormatPrice($effectivePrice))),
        'availabilityClass' => 'availability-label availability-label--' . $status,
        'availabilityLabel' => catalogAvailabilityLabel($status),
        'available'         => $available,
        'sku'               => $variant['sku'],
    ];
}

$selectedHasDiscount = $selectedVariant['discount_price'] !== null;
$selectedEffectivePrice = catalogEffectivePrice(
    (float) $selectedVariant['price'],
    $selectedHasDiscount ? (float) $selectedVariant['discount_price'] : null
);
$selectedStatus = catalogAvailabilityStatus(
    (int) $selectedVariant['stock_quantity'],
    (int) $selectedVariant['reserved_quantity']
);
$selectedAvailable = max(0, (int) $selectedVariant['stock_quantity'] - (int) $selectedVariant['reserved_quantity']);

// Фото из product_images (главное первым); нет фото — одна заглушка.
$galleryUrls = array_map('catalogImageUrl', $imagePaths !== [] ? $imagePaths : [null]);
$imageUrl = $galleryUrls[0];
$imageUrls = array_map(static fn (string $url): string => APP_URL . $url, $galleryUrls);

// JSON-LD — из тех же $selectedEffectivePrice/$selectedStatus, что рисует
// видимую цену/наличие ниже, не пересчитывается заново (dod-global.md).
$productSchema = renderProductSchema(
    [
        'name'        => $product['name'],
        'description' => $product['description'],
        'sku'         => $selectedVariant['sku'],
    ],
    $selectedEffectivePrice,
    $selectedStatus,
    $productUrl,
    $imageUrls
);

ob_start();
?>
<?= $breadcrumbSchema ?>
<?= $productSchema ?>
<?php if ($favoriteNotice !== null): ?>
    <div class="container mt-3">
        <div class="alert alert-info mb-0"><?= e($favoriteNotice) ?></div>
    </div>
<?php endif; ?>
<section class="banner" style="background-color: #fff8e5; background-image: url(/assets/img/banners/banner-catalog.png)">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="banner-text">
                    <h1 class="banner-title<?= $bannerTitleModifier ?>"><?= e((string) $product['name']) ?></h1>
                    <?php include __DIR__ . '/components/breadcrumbs.php'; ?>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="banner-img">
                    <div class="banner-img-1">
                        <img src="/assets/img/banners/banner-img-1.jpg" alt="">
                    </div>
                    <div class="banner-img-2">
                        <img src="/assets/img/banners/banner-img-2.jpg" alt="">
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<section class="gap no-bottom">
    <div class="container">
        <div class="row product-info-section">
            <div class="col-lg-5 p-0">
                <div class="pd-gallery">
                    <!-- Переключение миниатюр обрабатывает custom.js (.li-pd-imgs click) -->
                    <ul class="pd-imgs">
                        <?php foreach ($galleryUrls as $i => $galleryUrl): ?>
                            <li class="li-pd-imgs<?= $i === 0 ? ' nav-active' : '' ?>">
                                <a href="javascript:void(0)">
                                    <img alt="<?= e((string) $product['name']) ?><?= $i > 0 ? ' — фото ' . ($i + 1) : '' ?>" src="<?= e($galleryUrl) ?>">
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="pd-main-img">
                        <img id="NZoomImg" alt="<?= e((string) $product['name']) ?>" src="<?= e($imageUrl) ?>">
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="product-info p-60">
                    <h3><?= e((string) $product['name']) ?></h3>
                    <form
                        class="variations_form"
                        id="product-variants-form"
                        data-variants='<?= e(json_encode($variantsForJs, JSON_UNESCAPED_UNICODE)) ?>'
                        data-default-variant="<?= (int) $selectedVariant['id'] ?>"
                    >
                        <div class="stock">
                            <span class="price" id="product-price">
                                <?php if ($selectedHasDiscount): ?>
                                    <del><?= e(seoFormatPrice($selectedVariant['price'])) ?> ₽</del>
                                    <ins><?= e(seoFormatPrice($selectedEffectivePrice)) ?> ₽</ins>
                                <?php else: ?>
                                    <?= e(seoFormatPrice($selectedEffectivePrice)) ?> ₽
                                <?php endif; ?>
                            </span>
                            <h6
                                id="product-availability"
                                class="availability-label availability-label--<?= e($selectedStatus) ?>"
                            ><span><?= e(catalogAvailabilityLabel($selectedStatus)) ?></span></h6>
                        </div>

                        <?php if (count($variants) > 1): ?>
                            <div class="variant-select">
                                <h6>Вариант</h6>
                                <select name="variant" id="product-variant-select" class="nice-select w-100">
                                    <?php foreach ($variants as $variant): ?>
                                        <?php $label = $variant['attributes'] !== [] ? implode(', ', $variant['attributes']) : $variant['sku']; ?>
                                        <option
                                            value="<?= (int) $variant['id'] ?>"
                                            <?= (int) $variant['id'] === (int) $selectedVariant['id'] ? 'selected' : '' ?>
                                        ><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="quantity">
                            <h6>Количество</h6>
                            <input
                                type="number"
                                class="input-text"
                                id="product-quantity"
                                name="quantity"
                                form="product-cart-form"
                                step="1"
                                min="1"
                                max="<?= max(1, $selectedAvailable) ?>"
                                value="1"
                                <?= $selectedAvailable <= 0 ? 'disabled' : '' ?>
                            >
                        </div>
                        <div class="add-to-cart">
                            <button
                                type="submit"
                                form="product-cart-form"
                                class="button"
                                id="product-add-to-cart"
                                <?= $selectedAvailable <= 0 ? 'disabled' : '' ?>
                            >В корзину</button>
                            <!-- Кнопка вынесена из своей <form>: вложенные <form> в
                                 HTML недействительны, браузер удаляет тег вложенной
                                 <form> при разборе — кнопка оставалась без формы для
                                 отправки и не работала (тот же баг, что чинили для
                                 «В корзину» выше). Форма — ниже, вне .variations_form,
                                 связь через form="product-favorite-form". -->
                            <button
                                type="submit"
                                form="product-favorite-form"
                                class="heart-wishlist"
                                aria-label="<?= $isFavorite ? 'Убрать из избранного' : 'Добавить в избранное' ?>"
                            ><i class="fa-<?= $isFavorite ? 'solid' : 'regular' ?> fa-heart"></i></button>
                        </div>
                        <ul class="product_meta">
                            <li>
                                <span class="theme-bg-clr">Категория:</span>
                                <ul class="pd-cat">
                                    <li>
                                        <?php foreach ($categoryChain as $index => $node): ?>
                                            <a href="<?= e($breadcrumbUrls[$index]) ?>"><?= e((string) $node['name']) ?></a><?= $index < count($categoryChain) - 1 ? ',' : '' ?>
                                        <?php endforeach; ?>
                                    </li>
                                </ul>
                            </li>
                            <li>
                                <span class="theme-bg-clr">Артикул:</span>
                                <span id="product-sku"><?= e((string) $selectedVariant['sku']) ?></span>
                            </li>
                        </ul>
                    </form>
                    <!-- Формы «В корзину» и «В избранное» вынесены из
                         .variations_form: вложенные <form> в HTML не работают.
                         Поля/кнопки выше привязаны к ним атрибутом form="...";
                         variant_id в обеих подставляет product-variants.js при
                         смене Варианта. -->
                    <form method="post" action="/cart/add" id="product-cart-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="variant_id" id="cart-variant-id" value="<?= (int) $selectedVariant['id'] ?>">
                    </form>
                    <form method="post" action="/favorites/toggle" id="product-favorite-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="slug" value="<?= e($product['slug']) ?>">
                        <input type="hidden" name="variant_id" id="favorite-variant-id" value="<?= (int) $selectedVariant['id'] ?>">
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- Вкладки Характеристики/Описание — по просьбе пользователя вместо
     статичных таблиц-заглушек «Пищевая ценность»/«Нормы кормления»,
     которые раньше стояли отдельным блоком ниже (макет product-details.html
     их не содержал — своей вёрстки/стилей под вкладки в style.css нет,
     оформление в petpark.css). Bootstrap 5 Tab component — уже
     самохостящийся bootstrap.min.js (id/data-bs-* ниже), новый JS не
     писал (general.md — родные компоненты Bootstrap через их API, не
     свой велосипед). «Состав» — фейковые данные (реального состава по
     Товару в БД нет, ADR-005/product_attributes хранит вид/породу, не
     ингредиенты); нормы кормления перенесены сюда как есть из старого
     блока. Замена на настоящие данные по каждому Товару — отдельная
     задача (ИИ-разбор карточки поставщика), не в этом Таске. -->
<section class="gap no-top">
    <div class="container">
        <ul class="nav pd-tabs" id="pd-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link active"
                    id="pd-tab-specs-btn"
                    data-bs-toggle="tab"
                    data-bs-target="#pd-tab-specs"
                    type="button"
                    role="tab"
                    aria-controls="pd-tab-specs"
                    aria-selected="true"
                >Характеристики</button>
            </li>
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link"
                    id="pd-tab-description-btn"
                    data-bs-toggle="tab"
                    data-bs-target="#pd-tab-description"
                    type="button"
                    role="tab"
                    aria-controls="pd-tab-description"
                    aria-selected="false"
                >Описание</button>
            </li>
        </ul>
        <div class="tab-content pd-tab-content" id="pd-tabs-content">
            <div class="tab-pane fade show active" id="pd-tab-specs" role="tabpanel" aria-labelledby="pd-tab-specs-btn">
                <!-- Фейковые данные — состава по Товару в БД нет, см.
                     комментарий у секции выше. -->
                <div class="table-responsive">
                    <table class="table pd-specs-table">
                        <tbody>
                            <tr>
                                <th scope="row">Состав</th>
                                <td>Мясо и субпродукты птицы (26%), рис, кукуруза, рыбий жир, свекольный жом, витаминно-минеральный комплекс, консервант (токоферолы)</td>
                            </tr>
                            <tr>
                                <th scope="row">Гарантированный анализ</th>
                                <td>Протеин 21%, жир 12%, клетчатка 3%, зола 7%, влажность 10%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="tab-pane fade" id="pd-tab-description" role="tabpanel" aria-labelledby="pd-tab-description-btn">
                <?php if (($product['description'] ?? '') !== ''): ?>
                    <p class="pd-description"><?= e((string) $product['description']) ?></p>
                <?php endif; ?>
                <h4 class="pd-tab-subheading">Нормы кормления</h4>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">Вес питомца</th>
                                <th scope="col">Порций в день</th>
                                <th scope="col">Смешивать с паучем</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="noBorder">До 5 кг</td>
                                <td class="noBorder">1–2</td>
                                <td class="noBorder">100 г паучa</td>
                            </tr>
                            <tr>
                                <td class="noBorder">5–10 кг</td>
                                <td class="noBorder">2–3</td>
                                <td class="noBorder">100 г паучa</td>
                            </tr>
                            <tr>
                                <td class="noBorder">10–25 кг</td>
                                <td class="noBorder">1–2</td>
                                <td class="noBorder"></td>
                            </tr>
                            <tr>
                                <td class="noBorder">25 кг и более</td>
                                <td class="noBorder">2–3</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- Блок услуг — статичная декоративная вёрстка по макету
     product-details.html, одинаковая на карточках всех Товаров:
     модели данных под бронирование услуг в БД нет, заведена по
     просьбе пользователя для визуального соответствия макету. Видео из
     макета (fancybox + YouTube) заменено статичной заглушкой без
     плеера — реального видео нет. Без `no-top` тут получалось двойное
     расстояние (низ секции с вкладками + верх этой секции — 240px). -->
<section class="gap no-top">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="information">
                    <h3>Уход и сервис</h3>
                    <div class="boder-bar"></div>
                    <p>
                        Помимо доставки корма, PetPark предлагает услуги по уходу
                        за питомцем — от груминга до выгула. Подробности и запись
                        появятся на сайте отдельным разделом.
                    </p>
                    <div class="row mt-md-5">
                        <div class="col-lg-6 col-md-6">
                            <div class="pet-grooming">
                                <i><img src="/assets/img/welcome-to-1.png" alt="иконка груминга"></i>
                                <svg width="138" height="138" viewBox="0 0 673 673" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M9.82698 416.603C-19.0352 298.701 18.5108 173.372 107.497 90.7633L110.607 96.5197C24.3117 177.199 -12.311 298.935 15.0502 413.781L9.82698 416.603ZM89.893 565.433C172.674 654.828 298.511 692.463 416.766 663.224L414.077 658.245C298.613 686.363 175.954 649.666 94.9055 562.725L89.893 565.433ZM656.842 259.141C685.039 374.21 648.825 496.492 562.625 577.656L565.413 582.817C654.501 499.935 691.9 374.187 662.536 256.065L656.842 259.141ZM581.945 107.518C499.236 18.8371 373.997 -18.4724 256.228 10.5134L259.436 16.4515C373.888 -10.991 495.248 25.1518 576.04 110.708L581.945 107.518Z" fill="#940c69"></path>
                                </svg>
                                <h4>Груминг</h4>
                                <p>Стрижка, мытьё и чистка когтей — запись через сайт скоро.</p>
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <div class="pet-grooming">
                                <i><img src="/assets/img/welcome-to-2.png" alt="иконка выгула"></i>
                                <svg width="138" height="138" viewBox="0 0 673 673" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M9.82698 416.603C-19.0352 298.701 18.5108 173.372 107.497 90.7633L110.607 96.5197C24.3117 177.199 -12.311 298.935 15.0502 413.781L9.82698 416.603ZM89.893 565.433C172.674 654.828 298.511 692.463 416.766 663.224L414.077 658.245C298.613 686.363 175.954 649.666 94.9055 562.725L89.893 565.433ZM656.842 259.141C685.039 374.21 648.825 496.492 562.625 577.656L565.413 582.817C654.501 499.935 691.9 374.187 662.536 256.065L656.842 259.141ZM581.945 107.518C499.236 18.8371 373.997 -18.4724 256.228 10.5134L259.436 16.4515C373.888 -10.991 495.248 25.1518 576.04 110.708L581.945 107.518Z" fill="#940c69"></path>
                                </svg>
                                <h4>Выгул</h4>
                                <p>Прогулки с собакой, если хозяин занят — запись через сайт скоро.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="looking video position-relative">
                    <svg class="golo" width="510" height="510" viewBox="0 0 673 673" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M9.82698 416.603C-19.0352 298.701 18.5108 173.372 107.497 90.7633L110.607 96.5197C24.3117 177.199 -12.311 298.935 15.0502 413.781L9.82698 416.603ZM89.893 565.433C172.674 654.828 298.511 692.463 416.766 663.224L414.077 658.245C298.613 686.363 175.954 649.666 94.9055 562.725L89.893 565.433ZM656.842 259.141C685.039 374.21 648.825 496.492 562.625 577.656L565.413 582.817C654.501 499.935 691.9 374.187 662.536 256.065L656.842 259.141ZM581.945 107.518C499.236 18.8371 373.997 -18.4724 256.228 10.5134L259.436 16.4515C373.888 -10.991 495.248 25.1518 576.04 110.708L581.945 107.518Z" fill="#000"></path>
                    </svg>
                    <!-- Не фото Товара: реального видео/фото под этот блок нет,
                         честная заглушка вместо выдачи чужого контента за своё. -->
                    <div class="pd-video-placeholder" role="img" aria-label="Видео скоро">
                        <i class="fa-solid fa-video"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<section class="gap no-top">
    <div class="container">
        <div class="row mt-70">
            <div class="col-lg-7">
                <div class="information">
                    <h3>Отзывы</h3>
                    <div class="boder-bar"></div>
                </div>
                <?php if ($reviews === []): ?>
                    <p>Пока нет отзывов об этом товаре — станьте первым.</p>
                <?php else: ?>
                    <ul class="reviews">
                        <?php foreach ($reviews as $review): ?>
                            <li>
                                <div>
                                    <div class="star">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fa-<?= $i <= (int) $review['rating'] ? 'solid' : 'regular' ?> fa-star"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center">
                                        <h4><?= e((string) $review['author_name']) ?></h4>
                                        <span><?= e(date('d.m.Y', strtotime((string) $review['created_at']))) ?></span>
                                    </div>
                                    <p><?= e((string) $review['body']) ?></p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="col-lg-5">
                <?php if ($reviewSuccess !== null): ?>
                    <div class="alert alert-success"><?= e($reviewSuccess) ?></div>
                <?php elseif ($reviewError !== null): ?>
                    <div class="alert alert-danger"><?= e($reviewError) ?></div>
                <?php endif; ?>
                <form class="add-review comment leave-comment" method="post" action="/product/<?= e($product['slug']) ?>/review" novalidate>
                    <?= csrfField() ?>
                    <!--
                        Honeypot — скрыт классом .form-honeypot (display:none,
                        petpark.css), не атрибутом hidden (dod-global.md:
                        некоторые боты его пропускают). Заполнение — молчаливый
                        отказ, тот же ответ, что и у настоящего успеха.
                    -->
                    <input type="text" name="website" class="form-honeypot" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <input type="hidden" name="form_token" value="<?= e($reviewFormToken) ?>">
                    <div class="information">
                        <h3>Оставить отзыв</h3>
                        <div class="boder-bar"></div>
                    </div>
                    <fieldset class="star-rating-input d-flex align-items-center mb-4">
                        <legend class="visually-hidden">Оценка</legend>
                        <span>Оценка:</span>
                        <div class="star-rating ps-md-4">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" name="rating" id="rating-<?= $i ?>" value="<?= $i ?>" required>
                                <label for="rating-<?= $i ?>" title="<?= $i ?> из 5">★</label>
                            <?php endfor; ?>
                        </div>
                    </fieldset>
                    <input type="text" name="name" placeholder="Ваше имя" maxlength="100" required>
                    <input type="email" name="email" placeholder="Email" maxlength="150" required>
                    <textarea name="body" placeholder="Текст отзыва" required></textarea>
                    <button type="submit" class="button">Отправить отзыв</button>
                </form>
            </div>
        </div>
    </div>
</section>
<?php if ($similarProducts !== []): ?>
    <section class="gap no-top products-section">
        <div class="container">
            <div class="information">
                <h3>Похожие товары</h3>
                <div class="boder-bar"></div>
            </div>
            <div class="row">
                <?php foreach ($similarProducts as $product): ?>
                    <?php include __DIR__ . '/components/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/layouts/public.php';
