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
}
