<?php

declare(strict_types=1);

/**
 * Фасетные фильтры каталога/поиска — Характеристики (выпадающий список,
 * одно значение — Вкус/Объём-размер, единственные показанные в сайдбаре,
 * см. CATALOG_FILTERABLE_ATTRIBUTES в Core/Catalog.php), Бренд (чекбоксы),
 * цена (min/max + range-слайдер макета) — FR-CAT-002/003, phase-1.md
 * Таск 3. Общий компонент для catalog.php и search.php. Сабмит без
 * перезагрузки — public/assets/js/catalog.js (#catalog-filters-form);
 * без JS — обычный GET-submit на $actionPath.
 * @var array<string, array<int, string>> $attributeFacets attr_name => доступные значения (уже сужено до сайдбара)
 * @var array<int, array{slug: string, name: string}> $brands
 * @var array<string, array<int, string>> $selectedAttrs
 * @var array<int, string> $selectedBrands
 * @var float|null $priceMin
 * @var float|null $priceMax
 * @var array{min: float, max: float} $priceBounds
 * @var string $actionPath
 * @var string $sort
 * @var string|null $query Опционально — поисковый запрос (search.php), сохраняется скрытым полем
 */

$query ??= '';
$floor = (int) floor($priceBounds['min']);
$ceil = max($floor + 1, (int) ceil($priceBounds['max']));
$lower = $priceMin !== null ? (int) $priceMin : $floor;
$upper = $priceMax !== null ? (int) $priceMax : $ceil;
?>
<form method="get" action="<?= e($actionPath) ?>" class="catalog-filters" id="catalog-filters-form">
    <input type="hidden" name="sort" value="<?= e($sort) ?>">
    <?php if ($query !== ''): ?>
        <input type="hidden" name="q" value="<?= e($query) ?>">
    <?php endif; ?>

    <?php foreach ($attributeFacets as $attrName => $values): ?>
        <?php $selectedValue = $selectedAttrs[$attrName][0] ?? ''; ?>
        <div class="sidebar">
            <h3><?= e($attrName) ?></h3>
            <div class="boder-bar"></div>
            <select name="attr[<?= e($attrName) ?>]" class="nice-select w-100">
                <option value="">Любой</option>
                <?php foreach ($values as $value): ?>
                    <option value="<?= e($value) ?>" <?= $selectedValue === $value ? 'selected' : '' ?>>
                        <?= e($value) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endforeach; ?>

    <?php if ($brands !== []): ?>
        <div class="sidebar">
            <h3>Бренд</h3>
            <div class="boder-bar"></div>
            <ul class="category">
                <?php foreach ($brands as $brand): ?>
                    <?php $inputId = 'brand-' . $brand['slug']; ?>
                    <li>
                        <div class="form-check">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="brand[]"
                                value="<?= e($brand['slug']) ?>"
                                id="<?= e($inputId) ?>"
                                <?= in_array($brand['slug'], $selectedBrands, true) ? 'checked' : '' ?>
                            >
                            <label class="form-check-label" for="<?= e($inputId) ?>"><?= e($brand['name']) ?></label>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="sidebar">
        <h3>Цена</h3>
        <div class="boder-bar"></div>
        <div class="wrapper">
            <fieldset class="filter-price">
                <div class="price-wrap">
                    <span class="price-title">Цена, ₽</span>
                    <div class="price-wrap-1">
                        <input id="one" name="price_min" value="<?= $lower ?>" inputmode="numeric">
                        <label for="one">₽</label>
                    </div>
                    <div class="price-wrap_line">-</div>
                    <div class="price-wrap-2">
                        <input id="two" name="price_max" value="<?= $upper ?>" inputmode="numeric">
                        <label for="two">₽</label>
                    </div>
                </div>
                <div class="price-field">
                    <input type="range" min="<?= $floor ?>" max="<?= $ceil ?>" value="<?= $lower ?>" id="lower">
                    <input type="range" min="<?= $floor ?>" max="<?= $ceil ?>" value="<?= $upper ?>" id="upper">
                </div>
            </fieldset>
        </div>
    </div>

    <button type="submit" class="w-100 button">Применить фильтры</button>
</form>
