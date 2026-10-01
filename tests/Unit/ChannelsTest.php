<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ChannelsTest extends TestCase
{
    /** @return array<string, array{string, array<int, string>}> */
    public static function configs(): array
    {
        return [
            'все четыре'          => ['max,telegram,vk,avito', ['max', 'telegram', 'vk', 'avito']],
            'без avito'           => ['max,telegram,vk', ['max', 'telegram', 'vk']],
            'пробелы и регистр'   => [' Telegram , VK ', ['telegram', 'vk']],
            'неизвестный код'     => ['telegram,whatsapp', ['telegram']],
            'повторы'             => ['vk,vk,max', ['vk', 'max']],
            'пустая строка'       => ['', []],
            'только мусор'        => [' , ,x', []],
        ];
    }

    /** @param array<int, string> $expected */
    #[DataProvider('configs')]
    public function testEnabledChannels(string $configured, array $expected): void
    {
        $this->assertSame($expected, enabledChannels($configured));
    }
}
