<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductFormTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function slugCases(): array
    {
        return [
            'кириллица'             => ['Корм для кошек Royal Canin', 'korm-dlya-koshek-royal-canin'],
            'мягкий и твёрдый знак' => ['Объём, ёлка', 'obem-elka'],
            'лишние символы'        => ['  Лежанка  (XL) !!! ', 'lezhanka-xl'],
            'только символы'        => ['!!! ???', ''],
        ];
    }

    #[DataProvider('slugCases')]
    public function testSlugify(string $name, string $expected): void
    {
        $this->assertSame($expected, productSlugify($name));
    }

    public function testSlugifyIsCappedAndNeverEndsWithDash(): void
    {
        $slug = productSlugify(str_repeat('корм ', 100));

        $this->assertLessThanOrEqual(PRODUCT_SLUG_MAX, strlen($slug));
        $this->assertStringEndsNotWith('-', $slug);
        $this->assertTrue(productSlugIsValid($slug));
    }

    /** @return array<string, array{string, bool}> */
    public static function slugValidity(): array
    {
        return [
            'нормальный'      => ['korm-dlya-koshek', true],
            'с цифрами'       => ['royal-canin-2', true],
            'пустой'          => ['', false],
            'заглавные'       => ['Korm', false],
            'кириллица'       => ['корм', false],
            'двойной дефис'   => ['korm--koshek', false],
            'дефис по краям'  => ['-korm', false],
            'слэш'            => ['korm/koshek', false],
            'слишком длинный' => [str_repeat('a', PRODUCT_SLUG_MAX + 1), false],
        ];
    }

    #[DataProvider('slugValidity')]
    public function testSlugIsValid(string $slug, bool $expected): void
    {
        $this->assertSame($expected, productSlugIsValid($slug));
    }

    /** @return array{0: array<string, mixed>, 1: array<string, string>} */
    private function validateOwner(array $input, string $currentSlug = ''): array
    {
        return productFormValidate($input, true, $currentSlug, [1, 2, 3], [10, 11]);
    }

    public function testOwnerValidFormGeneratesSlugFromName(): void
    {
        [$values, $errors] = $this->validateOwner([
            'name'        => ' Корм для кошек ',
            'description' => 'Сухой корм',
            'category_id' => '2',
            'brand_id'    => '10',
        ]);

        $this->assertSame([], $errors);
        $this->assertSame('Корм для кошек', $values['name']);
        $this->assertSame('korm-dlya-koshek', $values['slug']);
        $this->assertSame(2, $values['category_id']);
        $this->assertSame(0, $values['secondary_category_id']);
        $this->assertSame(10, $values['brand_id']);
    }

    public function testEmptySlugOnEditKeepsCurrentSlug(): void
    {
        [$values, $errors] = $this->validateOwner(
            ['name' => 'Новое название', 'category_id' => '1', 'slug' => ''],
            'staryy-adres'
        );

        $this->assertSame([], $errors);
        $this->assertSame('staryy-adres', $values['slug']);
    }

    public function testRequiredFieldsAndUnknownIds(): void
    {
        [, $errors] = $this->validateOwner([
            'name'        => '',
            'slug'        => 'Bad Slug',
            'category_id' => '99',
            'brand_id'    => '55',
        ]);

        $this->assertSame(['name', 'slug', 'category_id', 'brand_id'], array_keys($errors));
    }

    public function testNameAndDescriptionLengthLimits(): void
    {
        [, $errors] = $this->validateOwner([
            'name'        => str_repeat('я', PRODUCT_NAME_MAX + 1),
            'description' => str_repeat('я', PRODUCT_DESCRIPTION_MAX + 1),
            'category_id' => '1',
        ]);

        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('description', $errors);
    }

    public function testSecondaryCategoryRules(): void
    {
        [$values, $errors] = $this->validateOwner(['name' => 'Т', 'category_id' => '1', 'secondary_category_id' => '3']);
        $this->assertSame([], $errors);
        $this->assertSame(3, $values['secondary_category_id']);

        [, $errors] = $this->validateOwner(['name' => 'Т', 'category_id' => '1', 'secondary_category_id' => '1']);
        $this->assertArrayHasKey('secondary_category_id', $errors);

        [, $errors] = $this->validateOwner(['name' => 'Т', 'category_id' => '1', 'secondary_category_id' => ['2', '3']]);
        $this->assertArrayHasKey('secondary_category_id', $errors);

        [, $errors] = $this->validateOwner(['name' => 'Т', 'category_id' => '1', 'secondary_category_id' => '99']);
        $this->assertArrayHasKey('secondary_category_id', $errors);
    }

    public function testNonStringInputIsTreatedAsEmpty(): void
    {
        [, $errors] = $this->validateOwner(['name' => ['x'], 'category_id' => ['1']]);

        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('category_id', $errors);
    }

    public function testFreelancerReadsOnlyNameAndDescription(): void
    {
        [$values, $errors] = productFormValidate([
            'name'                  => 'Лежанка',
            'description'           => 'Мягкая',
            'slug'                  => 'hacked',
            'category_id'           => '3',
            'secondary_category_id' => '2',
            'brand_id'              => '11',
            'is_active'             => '0',
        ], false, 'lezhanka', [1, 2, 3], [10, 11]);

        $this->assertSame([], $errors);
        $this->assertSame(['name' => 'Лежанка', 'description' => 'Мягкая'], $values);
    }

    public function testUploadedPathsExcludeDemoAndForeignPaths(): void
    {
        $own = 'products/' . str_repeat('a1', 16) . '.jpg';

        $this->assertSame(
            [$own],
            productUploadedPaths([
                $own,
                'products/demo/food-2.png',
                'returns/' . str_repeat('a1', 16) . '.jpg',
                'products/../secret.jpg',
                'products/' . str_repeat('a1', 16) . '.php',
            ])
        );
    }

    private const ATTRIBUTE_NAMES = ['вид_животного', 'возраст', 'назначение'];

    public function testAttributesTrimAndKeepNewValuesOutsideDictionary(): void
    {
        [$values, $errors] = productAttributesValidate(
            ['attributes' => ['вид_животного' => '  Кошка ', 'назначение' => 'для стерилизованных кошек']],
            self::ATTRIBUTE_NAMES
        );

        $this->assertSame([], $errors);
        $this->assertSame(['вид_животного' => 'Кошка', 'назначение' => 'для стерилизованных кошек'], $values);
    }

    public function testAttributesEmptyValueMeansRemove(): void
    {
        [$values] = productAttributesValidate(['attributes' => ['возраст' => '   ']], self::ATTRIBUTE_NAMES);

        $this->assertSame(['возраст' => ''], $values);
    }

    public function testAttributesAbsentNameIsNotTouched(): void
    {
        [$values] = productAttributesValidate(['attributes' => ['возраст' => 'Взрослые']], self::ATTRIBUTE_NAMES);

        $this->assertArrayNotHasKey('вид_животного', $values);
        $this->assertArrayNotHasKey('назначение', $values);
    }

    public function testAttributesDeleteButtonBeatsTypedValue(): void
    {
        [$values] = productAttributesValidate(
            ['attributes' => ['возраст' => 'Взрослые', 'назначение' => 'Корм'], 'delete' => 'возраст'],
            self::ATTRIBUTE_NAMES
        );

        $this->assertSame(['возраст' => '', 'назначение' => 'Корм'], $values);
    }

    public function testAttributesDeleteWorksEvenIfFieldNotPosted(): void
    {
        [$values] = productAttributesValidate(['delete' => 'назначение'], self::ATTRIBUTE_NAMES);

        $this->assertSame(['назначение' => ''], $values);
    }

    public function testAttributesUnknownNamesAreIgnored(): void
    {
        [$values] = productAttributesValidate(
            ['attributes' => ['цена' => '1', 'возраст' => 'Взрослые'], 'delete' => 'цена'],
            self::ATTRIBUTE_NAMES
        );

        $this->assertSame(['возраст' => 'Взрослые'], $values);
    }

    public function testAttributesRejectTooLongAndBrokenUtf8(): void
    {
        [, $errors] = productAttributesValidate(
            [
                'attributes' => [
                    'возраст'    => str_repeat('я', PRODUCT_ATTRIBUTE_VALUE_MAX + 1),
                    'назначение' => "\xFF\xFE",
                ],
            ],
            self::ATTRIBUTE_NAMES
        );

        $this->assertArrayHasKey('возраст', $errors);
        $this->assertArrayHasKey('назначение', $errors);
    }

    public function testAttributesMaxLengthAccepted(): void
    {
        [$values, $errors] = productAttributesValidate(
            ['attributes' => ['возраст' => str_repeat('я', PRODUCT_ATTRIBUTE_VALUE_MAX)]],
            self::ATTRIBUTE_NAMES
        );

        $this->assertSame([], $errors);
        $this->assertSame(PRODUCT_ATTRIBUTE_VALUE_MAX, mb_strlen($values['возраст']));
    }

    public function testAttributesNonArrayInputIsIgnored(): void
    {
        [$values, $errors] = productAttributesValidate(['attributes' => 'возраст'], self::ATTRIBUTE_NAMES);

        $this->assertSame([], $values);
        $this->assertSame([], $errors);
    }

    public function testAiSuggestionsKeepOnlyKnownNonEmptyStrings(): void
    {
        $this->assertSame(
            ['возраст' => 'Взрослые'],
            productAttributeSuggestions(
                ['возраст' => ' Взрослые ', 'назначение' => null, 'вид_животного' => '', 'цена' => '1', 'x' => ['a']],
                self::ATTRIBUTE_NAMES
            )
        );
    }
}
