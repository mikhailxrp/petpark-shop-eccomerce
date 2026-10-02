<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CatalogTest extends TestCase
{
    public function testAvailabilityStatusOutOfStockAtZeroOrBelow(): void
    {
        $this->assertSame('out', \catalogAvailabilityStatus(0, 0));
        $this->assertSame('out', \catalogAvailabilityStatus(3, 3));
    }

    public function testAvailabilityStatusLowBetweenOneAndFour(): void
    {
        $this->assertSame('low', \catalogAvailabilityStatus(4, 0));
        $this->assertSame('low', \catalogAvailabilityStatus(1, 0));
    }

    public function testAvailabilityStatusInStockAtThresholdAndAbove(): void
    {
        $this->assertSame('in', \catalogAvailabilityStatus(5, 0));
        $this->assertSame('in', \catalogAvailabilityStatus(10, 2));
    }

    public function testAvailabilityLabelMapsEachStatus(): void
    {
        $this->assertSame('Нет в наличии', \catalogAvailabilityLabel('out'));
        $this->assertSame('Осталось мало', \catalogAvailabilityLabel('low'));
        $this->assertSame('В наличии', \catalogAvailabilityLabel('in'));
    }

    public function testImageUrlPrefixesUploadsAndFallsBackWhenEmpty(): void
    {
        $this->assertSame('/uploads/products/demo/food-2.png', \catalogImageUrl('products/demo/food-2.png'));
        $this->assertSame(CATALOG_IMAGE_FALLBACK, \catalogImageUrl(null));
        $this->assertSame(CATALOG_IMAGE_FALLBACK, \catalogImageUrl(''));
    }

    public function testEffectivePriceUsesDiscountWhenSet(): void
    {
        $this->assertSame(799.0, \catalogEffectivePrice(990.0, 799.0));
    }

    public function testEffectivePriceFallsBackToRegularPriceWhenNoDiscount(): void
    {
        $this->assertSame(990.0, \catalogEffectivePrice(990.0, null));
    }

    public function testNormalizeSortAcceptsKnownValues(): void
    {
        $this->assertSame('price_asc', \catalogNormalizeSort('price_asc'));
        $this->assertSame('new', \catalogNormalizeSort('new'));
    }

    public function testNormalizeSortFallsBackToPopularityForGarbage(): void
    {
        $this->assertSame('popularity', \catalogNormalizeSort('DROP TABLE products'));
        $this->assertSame('popularity', \catalogNormalizeSort(null));
        $this->assertSame('popularity', \catalogNormalizeSort(['price_asc']));
    }

    public function testNormalizePageParsesDigitStrings(): void
    {
        $this->assertSame(3, \catalogNormalizePage('3'));
        $this->assertSame(1, \catalogNormalizePage(1));
    }

    public function testNormalizePageFallsBackToOneForInvalidInput(): void
    {
        $this->assertSame(1, \catalogNormalizePage('abc'));
        $this->assertSame(1, \catalogNormalizePage('-5'));
        $this->assertSame(1, \catalogNormalizePage(0));
        $this->assertSame(1, \catalogNormalizePage(null));
    }

    public function testBuildCategoryTreeNestsChildrenUnderParent(): void
    {
        $categories = [
            ['id' => 1, 'parent_id' => null, 'name' => 'Корма', 'slug' => 'korma'],
            ['id' => 2, 'parent_id' => 1, 'name' => 'Сухой корм', 'slug' => 'suhoy-korm'],
            ['id' => 3, 'parent_id' => null, 'name' => 'Аксессуары', 'slug' => 'aksessuary'],
        ];

        $tree = \catalogBuildCategoryTree($categories);

        $this->assertCount(2, $tree);
        $this->assertSame('Корма', $tree[0]['name']);
        $this->assertCount(1, $tree[0]['children']);
        $this->assertSame('Сухой корм', $tree[0]['children'][0]['name']);
        $this->assertSame([], $tree[1]['children']);
    }

    public function testDescendantCategoryIdsIncludesSelfAndAllChildren(): void
    {
        $categories = [
            ['id' => 1, 'parent_id' => null],
            ['id' => 2, 'parent_id' => 1],
            ['id' => 3, 'parent_id' => 1],
            ['id' => 4, 'parent_id' => 2],
            ['id' => 5, 'parent_id' => null],
        ];

        $ids = \catalogDescendantCategoryIds($categories, 1);

        sort($ids);
        $this->assertSame([1, 2, 3, 4], $ids);
    }

    public function testDescendantCategoryIdsOfLeafIsOnlyItself(): void
    {
        $categories = [
            ['id' => 1, 'parent_id' => null],
            ['id' => 2, 'parent_id' => 1],
        ];

        $this->assertSame([2], \catalogDescendantCategoryIds($categories, 2));
    }

    public function testCategoryChainBuildsRootToLeafOrder(): void
    {
        $categories = [
            ['id' => 1, 'parent_id' => null, 'name' => 'Корма', 'slug' => 'korma'],
            ['id' => 2, 'parent_id' => 1, 'name' => 'Сухой корм', 'slug' => 'suhoy-korm'],
        ];

        $chain = \catalogCategoryChain($categories, 2);

        $this->assertCount(2, $chain);
        $this->assertSame('Корма', $chain[0]['name']);
        $this->assertSame('Сухой корм', $chain[1]['name']);
    }

    public function testCanonicalPathForRootCatalog(): void
    {
        $this->assertSame('/catalog', \catalogCanonicalPath([]));
    }

    public function testCanonicalPathForCategoryChain(): void
    {
        $this->assertSame('/catalog/korma/suhoy-korm', \catalogCanonicalPath(['korma', 'suhoy-korm']));
    }

    public function testBreadcrumbUrlsAccumulatePath(): void
    {
        $this->assertSame(
            ['/catalog/korma', '/catalog/korma/suhoy-korm'],
            \catalogBreadcrumbUrls(['korma', 'suhoy-korm'])
        );
    }

    public function testBreadcrumbUrlsForRootCatalogIsEmpty(): void
    {
        $this->assertSame([], \catalogBreadcrumbUrls([]));
    }

    public function testAggregateCategoryCountsIncludesSubcategoryCounts(): void
    {
        $categories = [
            ['id' => 1, 'parent_id' => null],
            ['id' => 2, 'parent_id' => 1],
            ['id' => 3, 'parent_id' => 1],
            ['id' => 4, 'parent_id' => null],
        ];
        $directCounts = [1 => 2, 2 => 5, 3 => 1, 4 => 7];

        $counts = \catalogAggregateCategoryCounts($categories, $directCounts);

        $this->assertSame(8, $counts[1]);
        $this->assertSame(5, $counts[2]);
        $this->assertSame(1, $counts[3]);
        $this->assertSame(7, $counts[4]);
    }

    public function testAggregateCategoryCountsTreatsMissingCategoryAsZero(): void
    {
        $categories = [['id' => 1, 'parent_id' => null]];

        $counts = \catalogAggregateCategoryCounts($categories, []);

        $this->assertSame(0, $counts[1]);
    }

    public function testFilterableAttributeFacetsKeepsOnlyWhitelist(): void
    {
        $facets = [
            'Цвет' => ['красный'],
            'Объём/размер' => ['1кг', '2кг'],
            'вид_животного' => ['Кошка'],
            'Вкус' => ['курица'],
        ];

        $this->assertSame(
            ['Вкус' => ['курица']],
            \catalogFilterableAttributeFacets($facets)
        );
    }

    public function testFilterableAttributeFacetsDropsDenylistedValues(): void
    {
        $facets = ['Вкус' => ['курица', '100г тюбик', '40г таблетки', 'говядина']];

        $this->assertSame(
            ['Вкус' => ['курица', 'говядина']],
            \catalogFilterableAttributeFacets($facets)
        );
    }

    public function testFilterableAttributeFacetsDropsAttributeIfOnlyDenylistedValuesLeft(): void
    {
        $facets = ['Вкус' => ['100г тюбик', '40г таблетки']];

        $this->assertSame([], \catalogFilterableAttributeFacets($facets));
    }

    public function testFilterableAttributeFacetsSkipsMissingWhitelistedEntries(): void
    {
        $this->assertSame([], \catalogFilterableAttributeFacets(['Цвет' => ['красный']]));
    }

    private const KNOWN_ATTRIBUTES = [
        'вид_животного' => ['кошка', 'собака', 'птица'],
        'Цвет'          => ['красный', 'синий'],
    ];

    public function testNormalizeAttrFilterDropsUnknownAttrName(): void
    {
        $result = \catalogNormalizeAttrFilter(['возраст' => ['котёнок']], self::KNOWN_ATTRIBUTES);

        $this->assertSame([], $result);
    }

    public function testNormalizeAttrFilterDropsUnknownValues(): void
    {
        $result = \catalogNormalizeAttrFilter(
            ['вид_животного' => ['кошка', 'DROP TABLE products']],
            self::KNOWN_ATTRIBUTES
        );

        $this->assertSame(['вид_животного' => ['кошка']], $result);
    }

    public function testNormalizeAttrFilterPromotesSingleStringToArray(): void
    {
        $result = \catalogNormalizeAttrFilter(['вид_животного' => 'кошка'], self::KNOWN_ATTRIBUTES);

        $this->assertSame(['вид_животного' => ['кошка']], $result);
    }

    public function testNormalizeAttrFilterKeepsMultipleValuesOfOneCharacteristic(): void
    {
        $result = \catalogNormalizeAttrFilter(
            ['вид_животного' => ['кошка', 'собака'], 'Цвет' => ['красный']],
            self::KNOWN_ATTRIBUTES
        );

        $this->assertSame(
            ['вид_животного' => ['кошка', 'собака'], 'Цвет' => ['красный']],
            $result
        );
    }

    public function testNormalizeAttrFilterIgnoresNonArrayInput(): void
    {
        $this->assertSame([], \catalogNormalizeAttrFilter('кошка', self::KNOWN_ATTRIBUTES));
        $this->assertSame([], \catalogNormalizeAttrFilter(null, self::KNOWN_ATTRIBUTES));
    }

    public function testNormalizeBrandFilterDropsUnknownSlugs(): void
    {
        $result = \catalogNormalizeBrandFilter(['royal-canin', 'not-a-brand'], ['royal-canin', 'purina']);

        $this->assertSame(['royal-canin'], $result);
    }

    public function testNormalizeBrandFilterPromotesSingleStringToArray(): void
    {
        $result = \catalogNormalizeBrandFilter('royal-canin', ['royal-canin']);

        $this->assertSame(['royal-canin'], $result);
    }

    public function testNormalizeBrandFilterHandlesMissingInput(): void
    {
        $this->assertSame([], \catalogNormalizeBrandFilter(null, ['royal-canin']));
    }

    public function testNormalizePriceBoundAcceptsNumericValues(): void
    {
        $this->assertSame(100.0, \catalogNormalizePriceBound('100'));
        $this->assertSame(99.5, \catalogNormalizePriceBound(99.5));
        $this->assertSame(0.0, \catalogNormalizePriceBound(0));
    }

    public function testNormalizePriceBoundRejectsGarbage(): void
    {
        $this->assertNull(\catalogNormalizePriceBound('abc'));
        $this->assertNull(\catalogNormalizePriceBound(-10));
        $this->assertNull(\catalogNormalizePriceBound(null));
        $this->assertNull(\catalogNormalizePriceBound(['100']));
    }

    public function testNormalizeSearchQueryTrimsAndRequiresMinimumLength(): void
    {
        $this->assertSame('корм', \catalogNormalizeSearchQuery('  корм  '));
        $this->assertSame('', \catalogNormalizeSearchQuery('к'));
        $this->assertSame('', \catalogNormalizeSearchQuery(''));
        $this->assertSame('', \catalogNormalizeSearchQuery(null));
    }

    public function testFilterQueryParamsOmitsEmptyValues(): void
    {
        $this->assertSame([], \catalogFilterQueryParams([
            'attr' => [], 'brand' => [], 'price_min' => null, 'price_max' => null, 'q' => '',
        ]));
    }

    public function testFilterQueryParamsKeepsActiveValues(): void
    {
        $result = \catalogFilterQueryParams([
            'attr'      => ['вид_животного' => ['кошка']],
            'brand'     => ['royal-canin'],
            'price_min' => 100.0,
            'price_max' => null,
            'q'         => 'корм',
        ]);

        $this->assertSame([
            'attr'      => ['вид_животного' => ['кошка']],
            'brand'     => ['royal-canin'],
            'price_min' => 100.0,
            'q'         => 'корм',
        ], $result);
    }

    private function sampleVariants(): array
    {
        return [
            ['id' => 10, 'price' => 990.0, 'discount_price' => null],
            ['id' => 11, 'price' => 1200.0, 'discount_price' => 799.0],
            ['id' => 12, 'price' => 500.0, 'discount_price' => null],
        ];
    }

    public function testSelectVariantReturnsRequestedWhenPresent(): void
    {
        $variant = \catalogSelectVariant($this->sampleVariants(), 10);

        $this->assertSame(10, $variant['id']);
    }

    public function testSelectVariantAcceptsRequestedIdAsNumericString(): void
    {
        $variant = \catalogSelectVariant($this->sampleVariants(), '11');

        $this->assertSame(11, $variant['id']);
    }

    public function testSelectVariantFallsBackToCheapestEffectivePriceWhenRequestedIsNull(): void
    {
        // Вариант 12 (500, без Скидки) дешевле по эффективной цене, чем
        // Вариант 11 (1200 → 799 со Скидкой) и Вариант 10 (990).
        $variant = \catalogSelectVariant($this->sampleVariants(), null);

        $this->assertSame(12, $variant['id']);
    }

    public function testSelectVariantFallsBackToCheapestWhenRequestedIdIsUnknown(): void
    {
        // Чужой/несуществующий id — Вариант не найден среди переданных
        // (уже отфильтрованных по is_active=1 на уровне Model), поэтому
        // применяется тот же дефолт, что и при отсутствии ?variant=.
        $variant = \catalogSelectVariant($this->sampleVariants(), 999);

        $this->assertSame(12, $variant['id']);
    }

    public function testSelectVariantFallsBackForGarbageRequestedId(): void
    {
        $variant = \catalogSelectVariant($this->sampleVariants(), 'DROP TABLE products');

        $this->assertSame(12, $variant['id']);
    }

    public function testSelectVariantBreaksTiesByLowerId(): void
    {
        $variants = [
            ['id' => 5, 'price' => 500.0, 'discount_price' => null],
            ['id' => 3, 'price' => 500.0, 'discount_price' => null],
        ];

        $variant = \catalogSelectVariant($variants, null);

        $this->assertSame(3, $variant['id']);
    }

    public function testSelectVariantReturnsNullForEmptyList(): void
    {
        $this->assertNull(\catalogSelectVariant([], 10));
    }
}
