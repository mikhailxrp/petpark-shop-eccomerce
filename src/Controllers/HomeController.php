<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Главная страница — SCR-01 (phase-1.md, Таск 5): слайдер, блок Услуг,
 * «О компании», опубликованные отзывы. Меню Категорий в шапке
 * строится компонентом header.php само по себе (та же логика, что и
 * на всех остальных страницах), сюда не передаётся.
 */
final class HomeController
{
    private const REVIEWS_LIMIT = 6;
    private const CATEGORY_TAB_PRODUCTS_LIMIT = 6;

    public function index(): void
    {
        $categories = categoryAll();
        $rootCategories = catalogBuildCategoryTree($categories);

        // «Популярные товары» на главной (блок вкладок по Категориям, Home 2):
        // по 6 товаров из каждой корневой Категории (2 полных ряда по 3 —
        // карточка переиспользует components/product-card.php, тот же
        // `col-md-4`-грид, что в каталоге; 4 товара, как в макете, ломали
        // раскладку — 3 в ряд и один «висящий» в следующем ряду, пользователь
        // прислал скриншот) с учётом Подкатегорий (catalogDescendantCategoryIds(),
        // тот же принцип, что у листинга каталога), переиспользуем
        // productListByFilters() вместо нового SQL. Категорию без активных
        // Товаров пропускаем — пустая вкладка не нужна.
        $categoryTabs = [];
        foreach ($rootCategories as $rootCategory) {
            $categoryIds = catalogDescendantCategoryIds($categories, (int) $rootCategory['id']);
            $products = productListByFilters(
                $categoryIds,
                [],
                [],
                null,
                null,
                null,
                'popularity',
                self::CATEGORY_TAB_PRODUCTS_LIMIT,
                0
            );
            if ($products === []) {
                continue;
            }
            $categoryTabs[] = ['category' => $rootCategory, 'products' => $products];
        }

        render('home', [
            'reviews'          => reviewPublishedForHome(self::REVIEWS_LIMIT),
            'reviewsAggregate' => reviewPublishedAggregate(),
            'rootCategories'   => $rootCategories,
            'categoryTabs'     => $categoryTabs,
        ]);
    }
}
