<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    public function testEmptyOrNonStringQueryMeansNoFilter(): void
    {
        $this->assertNull(\clientSearchTerms(''));
        $this->assertNull(\clientSearchTerms("   \t "));
        $this->assertNull(\clientSearchTerms(null));
        $this->assertNull(\clientSearchTerms(['x']));
    }

    public function testNameQueryHasNoPhoneTerm(): void
    {
        $this->assertSame(['name' => 'Иван', 'phone' => null], \clientSearchTerms('  Иван '));
    }

    public function testPhoneQueryInAnyFormatReducesToDigits(): void
    {
        $this->assertSame(['name' => null, 'phone' => '7900'], \clientSearchTerms('+7 (900'));
        $this->assertSame(['name' => null, 'phone' => '900'], \clientSearchTerms('900'));
    }

    public function testFullNumberWithLeadingEightIsRewrittenToSeven(): void
    {
        $this->assertSame(['name' => null, 'phone' => '79001234567'], \clientSearchTerms('8 900 123-45-67'));
        $this->assertSame(['name' => null, 'phone' => '79001234567'], \clientSearchTerms('+7 (900) 123-45-67'));
    }

    public function testShortPartialNumberStartingWithEightIsNotRewritten(): void
    {
        $this->assertSame(['name' => null, 'phone' => '8900'], \clientSearchTerms('8900'));
    }

    public function testShortDigitsWithoutLettersFallBackToName(): void
    {
        $this->assertSame(['name' => '12', 'phone' => null], \clientSearchTerms('12'));
    }

    public function testLettersAndDigitsSearchBoth(): void
    {
        $this->assertSame(['name' => 'Иван 900', 'phone' => '900'], \clientSearchTerms('Иван 900'));
    }

    public function testLikeWildcardsAndBackslashAreEscaped(): void
    {
        $this->assertSame(['name' => '50\\%', 'phone' => null], \clientSearchTerms('50%'));
        $this->assertSame(['name' => 'а\\_б', 'phone' => null], \clientSearchTerms('а_б'));
        $this->assertSame(['name' => 'а\\\\б', 'phone' => null], \clientSearchTerms('а\\б'));
    }

    public function testLongQueryIsTruncated(): void
    {
        $terms = \clientSearchTerms(str_repeat('я', 500));

        $this->assertNotNull($terms);
        $this->assertSame(CLIENT_SEARCH_MAX_LENGTH, mb_strlen((string) $terms['name']));
    }

    public function testPageNormalization(): void
    {
        $this->assertSame(1, \clientNormalizePage('abc', 5));
        $this->assertSame(1, \clientNormalizePage('-3', 5));
        $this->assertSame(1, \clientNormalizePage(['2'], 5));
        $this->assertSame(1, \clientNormalizePage('0', 5));
        $this->assertSame(5, \clientNormalizePage('99', 5));
        $this->assertSame(3, \clientNormalizePage('3', 5));
        $this->assertSame(1, \clientNormalizePage('7', 0));
    }
}
