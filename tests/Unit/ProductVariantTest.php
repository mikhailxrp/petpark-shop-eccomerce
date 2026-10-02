<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductVariantTest extends TestCase
{
    /** @return array<string, array{string, string|null}> */
    public static function moneyCases(): array
    {
        return [
            'целое'              => ['1500', '1500.00'],
            'запятая'            => ['1500,5', '1500.50'],
            'пробелы-разряды'    => ['1 500.99', '1500.99'],
            'граница DECIMAL'    => ['99999999.99', '99999999.99'],
            'ноль'               => ['0', null],
            'ноль с копейками'   => ['0,00', null],
            'три знака'          => ['10.123', null],
            'буквы'              => ['abc', null],
            'отрицательная'      => ['-5', null],
            'пусто'              => ['', null],
            'больше DECIMAL'     => ['100000000', null],
        ];
    }

    #[DataProvider('moneyCases')]
    public function testMoneyNormalize(string $raw, ?string $expected): void
    {
        $this->assertSame($expected, productVariantMoney($raw));
    }

    /** @return array<string, mixed> */
    private static function newInput(array $override = []): array
    {
        return $override + [
            'sku'            => 'KORM-2KG',
            'price'          => '1500',
            'stock_quantity' => '10',
            'discount_price' => '',
            'attributes'     => ['вес_упаковки' => '2 кг', 'вкус' => 'курица'],
        ];
    }

    public function testNewVariantValid(): void
    {
        [$values, $errors] = productVariantValidate(self::newInput(), null);

        $this->assertSame([], $errors);
        $this->assertSame('KORM-2KG', $values['sku']);
        $this->assertSame('1500.00', $values['price']);
        $this->assertSame(10, $values['stock_quantity']);
        $this->assertNull($values['discount_price']);
        $this->assertSame(['вес_упаковки' => '2 кг', 'вкус' => 'курица'], $values['attributes']);
    }

    /** @return array<string, array{mixed}> */
    public static function badSkus(): array
    {
        return [
            'пусто'        => [''],
            'пробелы'      => ['   '],
            'пробел внутри' => ['KORM 2KG'],
            'кириллица'    => ['КОРМ-2'],
            'спецсимволы'  => ['KORM;DROP'],
            'длинный'      => [str_repeat('A', PRODUCT_VARIANT_SKU_MAX + 1)],
            'массив'       => [['A']],
        ];
    }

    #[DataProvider('badSkus')]
    public function testNewVariantRejectsBadSku(mixed $sku): void
    {
        [, $errors] = productVariantValidate(self::newInput(['sku' => $sku]), null);

        $this->assertArrayHasKey('sku', $errors);
    }

    public function testSkuMaxLengthAccepted(): void
    {
        [, $errors] = productVariantValidate(
            self::newInput(['sku' => str_repeat('A', PRODUCT_VARIANT_SKU_MAX)]),
            null
        );

        $this->assertArrayNotHasKey('sku', $errors);
    }

    public function testNewVariantRejectsBadPrice(): void
    {
        foreach (['', '0', 'abc', '10.123', '-1'] as $price) {
            [, $errors] = productVariantValidate(self::newInput(['price' => $price]), null);
            $this->assertArrayHasKey('price', $errors, "price={$price}");
        }
    }

    /** @return array<string, array{mixed, int|null}> */
    public static function stockCases(): array
    {
        return [
            'пусто — ноль'  => ['', 0],
            'ноль'          => ['0', 0],
            'обычный'       => ['25', 25],
            'максимум'      => [(string) PRODUCT_VARIANT_STOCK_MAX, PRODUCT_VARIANT_STOCK_MAX],
            'больше макс.'  => [(string) (PRODUCT_VARIANT_STOCK_MAX + 1), null],
            'отрицательный' => ['-1', null],
            'дробный'       => ['1.5', null],
            'буквы'         => ['abc', null],
            'массив'        => [['1'], null],
        ];
    }

    #[DataProvider('stockCases')]
    public function testNewVariantStock(mixed $raw, ?int $expected): void
    {
        [$values, $errors] = productVariantValidate(self::newInput(['stock_quantity' => $raw]), null);

        if ($expected === null) {
            $this->assertArrayHasKey('stock_quantity', $errors);
            return;
        }

        $this->assertArrayNotHasKey('stock_quantity', $errors);
        $this->assertSame($expected, $values['stock_quantity']);
    }

    public function testDiscountMustBeBelowPrice(): void
    {
        foreach (['1500', '1500.01', '2000'] as $discount) {
            [, $errors] = productVariantValidate(self::newInput(['discount_price' => $discount]), null);
            $this->assertArrayHasKey('discount_price', $errors, "discount={$discount}");
        }

        [$values, $errors] = productVariantValidate(self::newInput(['discount_price' => '1499,99']), null);
        $this->assertArrayNotHasKey('discount_price', $errors);
        $this->assertSame('1499.99', $values['discount_price']);
    }

    public function testDiscountRejectsGarbageAndZero(): void
    {
        foreach (['0', 'abc', '10.123', '-5'] as $discount) {
            [, $errors] = productVariantValidate(self::newInput(['discount_price' => $discount]), null);
            $this->assertArrayHasKey('discount_price', $errors, "discount={$discount}");
        }
    }

    public function testEmptyDiscountMeansNoDiscount(): void
    {
        [$values, $errors] = productVariantValidate(self::newInput(['discount_price' => '  ']), null);

        $this->assertArrayNotHasKey('discount_price', $errors);
        $this->assertNull($values['discount_price']);
    }

    public function testDiscountIsComparedInKopecksNotFloat(): void
    {
        // 0.1 + 0.2 != 0.3 во float; в копейках 30 < 30 неверно, 29 < 30 верно.
        [, $equal] = productVariantValidate(self::newInput(['price' => '0.30', 'discount_price' => '0.30']), null);
        [, $below] = productVariantValidate(self::newInput(['price' => '0.30', 'discount_price' => '0.29']), null);

        $this->assertArrayHasKey('discount_price', $equal);
        $this->assertArrayNotHasKey('discount_price', $below);
    }

    public function testExistingVariantReadsOnlyDiscountActivityAndAttributes(): void
    {
        [$values, $errors] = productVariantValidate(
            [
                'sku'            => 'HACK',
                'price'          => '1',
                'stock_quantity' => '999',
                'discount_price' => '900',
                'is_active'      => '1',
                'attributes'     => ['вкус' => 'рыба'],
            ],
            '1500.00'
        );

        $this->assertSame([], $errors);
        $this->assertArrayNotHasKey('sku', $values);
        $this->assertArrayNotHasKey('price', $values);
        $this->assertArrayNotHasKey('stock_quantity', $values);
        $this->assertSame('900.00', $values['discount_price']);
        $this->assertTrue($values['is_active']);
        $this->assertSame(['вкус' => 'рыба'], $values['attributes']);
    }

    public function testExistingVariantDiscountComparedWithStoredPrice(): void
    {
        [, $errors] = productVariantValidate(['discount_price' => '1500', 'price' => '99999'], '1500.00');

        $this->assertArrayHasKey('discount_price', $errors);
    }

    public function testExistingVariantInactiveWhenCheckboxAbsent(): void
    {
        [$values] = productVariantValidate(['discount_price' => ''], '1500.00');

        $this->assertFalse($values['is_active']);
        $this->assertNull($values['discount_price']);
    }

    public function testAttributesIgnoreUnknownNamesAndDropEmpty(): void
    {
        [$values, $errors] = productVariantValidate(
            self::newInput(['attributes' => ['вес_упаковки' => '  ', 'цвет' => 'красный', 'вкус' => ' утка ']]),
            null
        );

        $this->assertSame([], $errors);
        $this->assertSame(['вкус' => 'утка'], $values['attributes']);
    }

    public function testAttributeValueTooLong(): void
    {
        [, $errors] = productVariantValidate(
            self::newInput(['attributes' => ['вкус' => str_repeat('я', PRODUCT_VARIANT_ATTRIBUTE_MAX + 1)]]),
            null
        );

        $this->assertArrayHasKey('attributes', $errors);
    }

    public function testAttributesNotArrayIsIgnored(): void
    {
        [$values, $errors] = productVariantValidate(self::newInput(['attributes' => 'вкус']), null);

        $this->assertSame([], $errors);
        $this->assertSame([], $values['attributes']);
    }
}
