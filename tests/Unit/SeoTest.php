<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SeoTest extends TestCase
{
    public function testSeoTitleReturnsFilledValueAsIs(): void
    {
        $entity = ['seo_title' => 'Кастомный заголовок'];

        $this->assertSame('Кастомный заголовок', seoTitle('product', $entity));
    }

    public function testSeoDescriptionReturnsFilledValueAsIs(): void
    {
        $entity = ['seo_description' => 'Кастомное описание'];

        $this->assertSame('Кастомное описание', seoDescription('product', $entity));
    }

    public function testSeoTitleFallsBackForProduct(): void
    {
        $entity = ['name' => 'Корм для кошек', 'price' => '990.00'];

        $title = seoTitle('product', $entity);

        $this->assertStringContainsString('Корм для кошек', $title);
        $this->assertStringContainsString('990', $title);
        $this->assertStringContainsString(SHOP_NAME, $title);
    }

    public function testSeoTitleFallsBackForCategory(): void
    {
        $entity = ['name' => 'Корма'];

        $title = seoTitle('category', $entity);

        $this->assertStringContainsString('Корма', $title);
        $this->assertStringContainsString(SHOP_NAME, $title);
        $this->assertStringContainsString(SHOP_CITY, $title);
    }

    public function testSeoTitleFallsBackForContentPageUsesTitleColumn(): void
    {
        $entity = ['title' => 'О компании'];

        $this->assertSame('О компании', seoTitle('content_page', $entity));
    }

    public function testSeoTitleFallsBackForGenericIsNeverEmpty(): void
    {
        $this->assertNotSame('', seoTitle('generic'));
    }

    public function testSeoDescriptionFallsBackForProduct(): void
    {
        $entity = ['name' => 'Корм для кошек'];

        $description = seoDescription('product', $entity);

        $this->assertStringContainsString('Корм для кошек', $description);
        $this->assertStringContainsString(SHOP_CITY, $description);
    }

    public function testSeoDescriptionFallsBackForContentPageUsesFirstWordsOfBody(): void
    {
        $body = implode(' ', array_fill(0, 40, 'слово'));

        $description = seoDescription('content_page', ['body' => $body]);

        $this->assertStringEndsWith('…', $description);
        $this->assertLessThan(strlen($body), strlen($description));
    }

    public function testSeoDescriptionFallsBackForGenericIsNeverEmpty(): void
    {
        $this->assertNotSame('', seoDescription('generic'));
    }

    public function testSeoDescriptionFallsBackForCategory(): void
    {
        $entity = ['name' => 'Корма'];

        $description = seoDescription('category', $entity);

        $this->assertStringContainsString('Корма', $description);
        $this->assertStringContainsString(SHOP_NAME, $description);
        $this->assertStringContainsString(SHOP_CITY, $description);
    }

    public function testSeoDescriptionDiffersFromTitleForEachType(): void
    {
        $product = ['name' => 'Корм для кошек', 'price' => '990.00'];
        $this->assertNotSame(seoTitle('product', $product), seoDescription('product', $product));

        $category = ['name' => 'Корма'];
        $this->assertNotSame(seoTitle('category', $category), seoDescription('category', $category));

        $this->assertNotSame(seoTitle('generic'), seoDescription('generic'));
    }
}
