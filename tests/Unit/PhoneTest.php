<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneTest extends TestCase
{
    /** @return array<string, array{string, string|null}> */
    public static function phones(): array
    {
        return [
            'плюс семь'            => ['+79000000000', '+79000000000'],
            'восьмёрка'            => ['89000000000', '+79000000000'],
            'пробелы и дефисы'     => ['+7 900 000-00-00', '+79000000000'],
            'скобки'               => ['8 (900) 000-00-00', '+79000000000'],
            'десять цифр'          => ['9000000000', '+79000000000'],
            'ник в Telegram'       => ['@ivan_petrov', null],
            'пустая строка'        => ['', null],
            'слишком короткий'     => ['+7900', null],
            'чужой код страны'     => ['+380501234567', null],
        ];
    }

    #[DataProvider('phones')]
    public function testNormalizePhone(string $raw, ?string $expected): void
    {
        $this->assertSame($expected, normalizePhone($raw));
    }
}
