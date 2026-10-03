<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContentHtmlTest extends TestCase
{
    public function testAllowedTagsAreKept(): void
    {
        $html = '<h2>Заголовок</h2><p>Текст <strong>важно</strong> и <em>курсив</em></p>'
            . '<h3>Список</h3><ul><li>раз</li></ul><ol><li>два</li></ol>';

        $this->assertSame($html, contentHtmlSanitize($html));
    }

    public function testScriptIsRemovedWithContent(): void
    {
        $result = contentHtmlSanitize('<p>Привет</p><script>alert(1)</script>');

        $this->assertSame('<p>Привет</p>', $result);
    }

    public function testEventHandlerAttributesAreStripped(): void
    {
        $result = contentHtmlSanitize('<p onclick="x()" style="color:red">Текст</p><img src="x" onerror="alert(1)">');

        $this->assertSame('<p>Текст</p>', $result);
        $this->assertStringNotContainsString('onerror', $result);
    }

    public function testDisallowedTagIsUnwrapped(): void
    {
        $result = contentHtmlSanitize('<div><p>Внутри</p></div><span>текст</span>');

        $this->assertSame('<p>Внутри</p>текст', $result);
    }

    public function testIframeIsRemoved(): void
    {
        $this->assertSame('', contentHtmlSanitize('<iframe src="https://evil.example"></iframe>'));
    }

    #[DataProvider('unsafeHrefProvider')]
    public function testUnsafeHrefIsDropped(string $href): void
    {
        $result = contentHtmlSanitize('<a href="' . $href . '">ссылка</a>');

        $this->assertSame('<a>ссылка</a>', $result);
    }

    /** @return array<string, array{string}> */
    public static function unsafeHrefProvider(): array
    {
        return [
            'javascript'       => ['javascript:alert(1)'],
            'javascript upper' => ['JaVaScRiPt:alert(1)'],
            'javascript tab'   => ["java\tscript:alert(1)"],
            'data'             => ['data:text/html;base64,PHNjcmlwdD4='],
            'protocol-relative' => ['//evil.example/x'],
            'empty'            => [''],
        ];
    }

    #[DataProvider('safeHrefProvider')]
    public function testSafeHrefIsKept(string $href): void
    {
        $result = contentHtmlSanitize('<a href="' . $href . '" target="_blank">ссылка</a>');

        $this->assertSame('<a href="' . $href . '">ссылка</a>', $result);
    }

    /** @return array<string, array{string}> */
    public static function safeHrefProvider(): array
    {
        return [
            'https'    => ['https://petpark.example/about'],
            'http'     => ['http://petpark.example'],
            'mailto'   => ['mailto:shop@petpark.example'],
            'tel'      => ['tel:+78630000000'],
            'relative' => ['/contacts'],
            'anchor'   => ['#team'],
        ];
    }

    public function testCommentsAreRemoved(): void
    {
        $this->assertSame('<p>a</p>', contentHtmlSanitize('<p>a</p><!-- секрет -->'));
    }

    public function testEmptyInputGivesEmptyString(): void
    {
        $this->assertSame('', contentHtmlSanitize('   '));
    }

    public function testPlainTextAndEntitiesStayEscaped(): void
    {
        $this->assertSame('<p>Кошки &amp; собаки &lt;3</p>', contentHtmlSanitize('<p>Кошки &amp; собаки &lt;3</p>'));
    }
}
