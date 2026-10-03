<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MarketplaceTest extends TestCase
{
    public function testPriceAddsMarkup(): void
    {
        $this->assertSame('1150.00', \marketplacePrice('1000.00'));
    }

    public function testDiscountReplacesPriceAndRoundsUpToRuble(): void
    {
        $this->assertSame('1034.00', \marketplacePrice('1000.00', '899.00')); // 1033.85 вверх
    }

    public function testFractionalKopecksRoundUp(): void
    {
        $this->assertSame('1151.00', \marketplacePrice('1000.01'));
    }

    public function testExactRubleIsNotRoundedUpExtra(): void
    {
        $this->assertSame('2300.00', \marketplacePrice('2000.00'));
        $this->assertSame('115.00', \marketplacePrice('100.00'));
    }

    public function testNegativeMarkupIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        \marketplacePrice('100.00', null, -1);
    }

    public function testListableWithAvailableStock(): void
    {
        $this->assertTrue(\marketplaceIsListable(5, 2, true, true));
    }

    public function testNotListableWithoutAvailableStock(): void
    {
        $this->assertFalse(\marketplaceIsListable(0, 0, true, true));
        $this->assertFalse(\marketplaceIsListable(3, 3, true, true));
    }

    public function testNotListableWhenVariantOrProductInactive(): void
    {
        $this->assertFalse(\marketplaceIsListable(5, 0, false, true));
        $this->assertFalse(\marketplaceIsListable(5, 0, true, false));
    }

    public function testKnownMarketplacesPass(): void
    {
        foreach (MARKETPLACES as $marketplace) {
            \marketplaceAssertKnown($marketplace);
        }
        $this->addToAssertionCount(1);
    }

    public function testUnknownMarketplaceThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        \marketplaceAssertKnown('amazon');
    }
}
