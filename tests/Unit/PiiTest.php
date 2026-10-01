<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once ROOT_PATH . '/src/Core/Pii.php';

final class PiiTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function phoneProvider(): array
    {
        return [
            '+7 со скобками'      => ['Мой номер +7 (900) 123-45-67, звоните'],
            '8 слитно'            => ['Мой номер 89001234567, звоните'],
            '8 с пробелами'       => ['Мой номер 8 900 123 45 67, звоните'],
            '+7 слитно'           => ['Мой номер +79001234567, звоните'],
            '8 со скобками'       => ['Мой номер 8(900)123-45-67, звоните'],
            'без кода страны'     => ['Мой номер 900 123 45 67, звоните'],
        ];
    }

    #[DataProvider('phoneProvider')]
    public function testPhoneIsStripped(string $text): void
    {
        $result = piiStripPhones($text);

        self::assertStringNotContainsString('900', $result);
        self::assertStringNotContainsString('67', $result);
        self::assertStringContainsString(PII_PHONE_PLACEHOLDER, $result);
        self::assertStringContainsString('Мой номер', $result);
        self::assertStringContainsString('звоните', $result);
    }

    public function testOrderTextIsNotTouchedAsPhone(): void
    {
        $text = 'Хочу корм для стерилизованной кошки, 2 кг, 3 упаковки за 1500 руб';

        self::assertSame($text, piiRedact($text));
    }

    public function testAddressIsStripped(): void
    {
        $result = piiStripAddress('Доставьте на ул. Ленина, д. 5, кв. 12 после шести');

        self::assertStringNotContainsString('Ленина', $result);
        self::assertStringNotContainsString('5', $result);
        self::assertStringNotContainsString('12', $result);
        self::assertSame('Доставьте на ' . PII_ADDRESS_PLACEHOLDER . ' после шести', $result);
    }

    public function testStreetNameDoesNotSwallowFollowingWords(): void
    {
        $result = piiStripAddress('Живу на улице Садовая привезите корм');

        self::assertStringNotContainsString('Садовая', $result);
        self::assertStringContainsString('привезите корм', $result);
    }

    public function testNameStaysWhilePhoneAndAddressAreRemoved(): void
    {
        $result = piiRedact('Меня зовут Анна, пр-т Буденновский, д. 10, +7 900 123-45-67. Нужен корм.');

        self::assertStringContainsString('Анна', $result);
        self::assertStringContainsString('Нужен корм.', $result);
        self::assertStringNotContainsString('Буденновский', $result);
        self::assertStringNotContainsString('900', $result);
        self::assertStringNotContainsString('10', $result);
    }

    public function testEmptyAndPlainTextAreStable(): void
    {
        self::assertSame('', piiRedact(''));
        self::assertSame('Здравствуйте, привет!', piiRedact('Здравствуйте, привет!'));
    }
}
