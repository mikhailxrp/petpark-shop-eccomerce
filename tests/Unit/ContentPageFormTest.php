<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ContentPageFormTest extends TestCase
{
    /** @return array<string, string> */
    private static function valid(): array
    {
        return [
            'title'           => 'О компании',
            'body'            => '<p>Мы любим питомцев.</p>',
            'seo_title'       => '',
            'seo_description' => '',
        ];
    }

    public function testValidFormHasNoErrors(): void
    {
        $result = contentPageFormValidate(self::valid());

        $this->assertSame([], $result['errors']);
        $this->assertSame('О компании', $result['values']['title']);
        $this->assertSame('<p>Мы любим питомцев.</p>', $result['values']['body']);
    }

    public function testBodyIsSanitizedOnSave(): void
    {
        $input = ['body' => '<p onclick="x()">Текст</p><script>alert(1)</script><a href="javascript:alert(1)">ссылка</a>'] + self::valid();

        $body = contentPageFormValidate($input)['values']['body'];

        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringContainsString('Текст', $body);
    }

    public function testEmptyTitleAndBodyAreRejected(): void
    {
        $result = contentPageFormValidate(['title' => '  ', 'body' => '<script>x</script>'] + self::valid());

        $this->assertArrayHasKey('title', $result['errors']);
        $this->assertArrayHasKey('body', $result['errors']);
    }

    public function testSeoFieldLengthLimits(): void
    {
        $atLimit = contentPageFormValidate([
            'seo_title'       => str_repeat('я', CONTENT_PAGE_SEO_TITLE_MAX),
            'seo_description' => str_repeat('я', CONTENT_PAGE_SEO_DESCRIPTION_MAX),
        ] + self::valid());
        $this->assertSame([], $atLimit['errors']);

        $over = contentPageFormValidate([
            'seo_title'       => str_repeat('я', CONTENT_PAGE_SEO_TITLE_MAX + 1),
            'seo_description' => str_repeat('я', CONTENT_PAGE_SEO_DESCRIPTION_MAX + 1),
        ] + self::valid());
        $this->assertArrayHasKey('seo_title', $over['errors']);
        $this->assertArrayHasKey('seo_description', $over['errors']);
    }

    public function testTitleTooLongIsRejected(): void
    {
        $result = contentPageFormValidate(['title' => str_repeat('я', CONTENT_PAGE_TITLE_MAX + 1)] + self::valid());

        $this->assertArrayHasKey('title', $result['errors']);
    }

    public function testNonStringInputDoesNotBreak(): void
    {
        $result = contentPageFormValidate(['title' => ['x'], 'body' => ['y']]);

        $this->assertArrayHasKey('title', $result['errors']);
        $this->assertArrayHasKey('body', $result['errors']);
    }

    public function testOnlyAboutHasGallery(): void
    {
        $this->assertTrue(contentPageHasGallery('about'));
        foreach (['contacts', 'privacy', 'offer'] as $slug) {
            $this->assertFalse(contentPageHasGallery($slug));
        }
    }
}
