<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once ROOT_PATH . '/src/Services/Ai/DescriptionGenerator.php';

final class DescriptionGeneratorTest extends TestCase
{
    private const PRODUCT = [
        'id'            => 7,
        'name'          => 'Корм Brit Care для кошек',
        'category_name' => 'Корм для кошек',
        'brand_name'    => 'Brit',
    ];

    public function testPromptContainsOnlyAllowedFields(): void
    {
        $prompt = descriptionPrompt(
            self::PRODUCT + ['price' => '1299.00', 'stock_quantity' => 42, 'description' => 'СТАРОЕ ОПИСАНИЕ'],
            ['вид_животного' => 'кошка', 'назначение' => 'для стерилизованных']
        );

        $this->assertStringContainsString('Корм Brit Care для кошек', $prompt);
        $this->assertStringContainsString('Категория: Корм для кошек', $prompt);
        $this->assertStringContainsString('Бренд: Brit', $prompt);
        $this->assertStringContainsString('вид животного: кошка', $prompt);
        $this->assertStringContainsString('назначение: для стерилизованных', $prompt);
        $this->assertStringNotContainsString('1299', $prompt);
        $this->assertStringNotContainsString('42', $prompt);
        $this->assertStringNotContainsString('СТАРОЕ ОПИСАНИЕ', $prompt);
    }

    public function testPromptWithoutBrandAndAttributesUsesNameAndCategoryOnly(): void
    {
        $prompt = descriptionPrompt(['name' => 'Лежанка', 'category_name' => 'Аксессуары', 'brand_name' => null], []);

        $this->assertStringContainsString('Товар: Лежанка', $prompt);
        $this->assertStringContainsString('Категория: Аксессуары', $prompt);
        $this->assertStringNotContainsString('Бренд', $prompt);
    }

    public function testCleanStripsMarkdownAndFences(): void
    {
        $clean = descriptionClean("```\n# Заголовок\n**Отличный** корм.\n\n\n\nВторой абзац.\n```");

        $this->assertSame("Заголовок\nОтличный корм.\n\nВторой абзац.", $clean);
    }

    public function testCleanReturnsNullForEmptyResponse(): void
    {
        $this->assertNull(descriptionClean("  \n ``` \n"));
    }

    public function testCleanTruncatesToMaxLength(): void
    {
        $clean = descriptionClean(str_repeat('я', DESCRIPTION_MAX_LENGTH + 500));

        $this->assertSame(DESCRIPTION_MAX_LENGTH, mb_strlen((string) $clean));
    }

    public function testNormalizeUnifiesLineBreaksSoEditDetectionIsStable(): void
    {
        $this->assertSame("a\nb", descriptionNormalize("  a\r\nb \r\n"));
    }
}
