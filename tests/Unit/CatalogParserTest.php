<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/database/seed-data/catalog-parser.php';

final class CatalogParserTest extends TestCase
{
    public function testSlugifyTransliteratesAndNormalizes(): void
    {
        $this->assertSame('vetlayn-sterilayz', \slugify('ВетЛайн Стерилайз'));
        $this->assertSame('40x40sm', \slugify('40x40см'));
        $this->assertSame('koshka-i-sobaka', \slugify('  Кошка  и  Собака!!'));
    }

    public function testClassifyVariantDimensionDetectsSizeByUnit(): void
    {
        $this->assertSame('Объём/размер', \classifyVariantDimension(['400г', '2кг'], 'Корма'));
    }

    public function testClassifyVariantDimensionDetectsLetterSize(): void
    {
        $this->assertSame('Размер', \classifyVariantDimension(['S', 'M', 'L'], 'Аксессуары'));
    }

    public function testClassifyVariantDimensionDetectsColor(): void
    {
        $this->assertSame('Цвет', \classifyVariantDimension(['серый', 'бежевый'], 'Аксессуары'));
    }

    public function testClassifyVariantDimensionFallsBackToTasteForFoodCategories(): void
    {
        $this->assertSame('Вкус', \classifyVariantDimension(['курица', 'рыба'], 'Корма'));
    }

    public function testClassifyVariantDimensionFallsBackToFeatureForNonFoodCategories(): void
    {
        $this->assertSame('Особенность', \classifyVariantDimension(['с колокольчиком'], 'Аксессуары'));
    }

    public function testParseVariantDimensionsSplitsTwoSegments(): void
    {
        $dimensions = \parseVariantDimensions('400г, 2кг, 10кг; курица / рыба', 'Корма');

        $this->assertCount(2, $dimensions);
        $this->assertSame('Объём/размер', $dimensions[0]['attr_name']);
        $this->assertSame(['400г', '2кг', '10кг'], $dimensions[0]['values']);
        $this->assertSame('Вкус', $dimensions[1]['attr_name']);
        $this->assertSame(['курица', 'рыба'], $dimensions[1]['values']);
    }

    public function testParseVariantDimensionsStripsTrailingSizeLabelWord(): void
    {
        $dimensions = \parseVariantDimensions('S / M / L размер', 'Лакомства');

        $this->assertCount(1, $dimensions);
        $this->assertSame('Размер', $dimensions[0]['attr_name']);
        $this->assertSame(['S', 'M', 'L'], $dimensions[0]['values']);
    }

    public function testBuildVariantCombinationsReturnsCartesianProduct(): void
    {
        $dimensions = [
            ['attr_name' => 'Объём/размер', 'values' => ['400г', '2кг']],
            ['attr_name' => 'Вкус', 'values' => ['курица', 'рыба']],
        ];

        $combinations = \buildVariantCombinations($dimensions);

        $this->assertCount(4, $combinations);
        $this->assertSame(['Объём/размер' => '400г', 'Вкус' => 'курица'], $combinations[0]);
        $this->assertSame(['Объём/размер' => '2кг', 'Вкус' => 'рыба'], $combinations[3]);
    }

    public function testBuildVariantCombinationsReturnsSingleEmptyComboWhenNoDimensions(): void
    {
        $this->assertSame([[]], \buildVariantCombinations([]));
    }

    public function testVariantPriceMultiplierGrowsWithSizeIndex(): void
    {
        $dimensions = [['attr_name' => 'Объём/размер', 'values' => ['400г', '2кг', '10кг']]];

        $this->assertSame(1.0, \variantPriceMultiplier(['Объём/размер' => '400г'], $dimensions));
        $this->assertSame(1.5, \variantPriceMultiplier(['Объём/размер' => '2кг'], $dimensions));
        $this->assertSame(2.0, \variantPriceMultiplier(['Объём/размер' => '10кг'], $dimensions));
    }

    public function testVariantPriceMultiplierIgnoresNonSizeDimensions(): void
    {
        $dimensions = [['attr_name' => 'Вкус', 'values' => ['курица', 'рыба']]];

        $this->assertSame(1.0, \variantPriceMultiplier(['Вкус' => 'рыба'], $dimensions));
    }

    public function testDistributeStockGivesRemainderToFirstVariants(): void
    {
        $this->assertSame([15, 14, 14], \distributeStock(43, 3));
    }

    public function testDistributeStockSplitsEvenlyWhenDivisible(): void
    {
        $this->assertSame([10, 10], \distributeStock(20, 2));
    }

    public function testDistributeStockReturnsEmptyArrayForZeroVariants(): void
    {
        $this->assertSame([], \distributeStock(10, 0));
    }

    public function testRoundToNearestTen(): void
    {
        $this->assertSame(1450.0, \roundToNearestTen(1449.0));
        $this->assertSame(1460.0, \roundToNearestTen(1455.0));
    }
}
