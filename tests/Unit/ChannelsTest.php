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

    private const LABELS = ['telegram' => 'Telegram', 'max' => 'MAX', 'vk' => 'VK'];

    public function testMessengerLinksIntersectEnabledChannelsWithUrls(): void
    {
        $links = messengerLinks(
            'max,telegram,vk,avito',
            ['telegram' => 'https://t.me/shop', 'max' => 'https://max.ru/shop', 'vk' => ''],
            self::LABELS
        );

        $this->assertSame([
            ['code' => 'max', 'label' => 'MAX', 'url' => 'https://max.ru/shop'],
            ['code' => 'telegram', 'label' => 'Telegram', 'url' => 'https://t.me/shop'],
        ], $links);
    }

    public function testMessengerLinksSkipDisabledChannelEvenWithUrl(): void
    {
        $links = messengerLinks('telegram', ['telegram' => 'https://t.me/shop', 'vk' => 'https://vk.com/shop'], self::LABELS);

        $this->assertSame(['telegram'], array_column($links, 'code'));
    }

    public function testMessengerLinksIgnoreAvitoAndConfigNoise(): void
    {
        $links = messengerLinks(' VK , avito ,vk', ['vk' => ' https://vk.com/shop ', 'avito' => 'https://avito.ru/x'], self::LABELS);

        $this->assertSame([['code' => 'vk', 'label' => 'VK', 'url' => 'https://vk.com/shop']], $links);
    }

    public function testMessengerLinksSkipInvalidUrlsFromDatabase(): void
    {
        $links = messengerLinks(
            'max,telegram,vk',
            ['max' => 'https://max/petpark', 'telegram' => 'https://t.me/petpark', 'vk' => 'javascript:alert(1)'],
            self::LABELS
        );

        $this->assertSame(['telegram'], array_column($links, 'code'));
    }

    public function testMessengerLinksEmptyWhenNothingConfigured(): void
    {
        $this->assertSame([], messengerLinks('max,telegram,vk', [], self::LABELS));
        $this->assertSame([], messengerLinks('', ['vk' => 'https://vk.com/shop'], self::LABELS));
    }

    /** @return array<string, array{string, bool}> */
    public static function messengerUrls(): array
    {
        return [
            'https'              => ['https://t.me/petpark', true],
            'https с путём'      => ['https://vk.com/club123?from=site', true],
            'http'               => ['http://t.me/petpark', false],
            'javascript'         => ['javascript:alert(1)', false],
            'data'               => ['data:text/html,<b>x</b>', false],
            'без схемы'          => ['t.me/petpark', false],
            'схема без хоста'    => ['https://', false],
            'пробел внутри'      => ['https://t.me/pet park', false],
            'перенос строки'     => ["https://t.me/a
b", false],
            'хост без домена'    => ['https://vk/petpark_rostov.', false],
            'хост с точкой в конце' => ['https://t.me./petpark', false],
            'пусто'              => ['', false],
            'длиннее лимита'     => ['https://t.me/' . str_repeat('a', 300), false],
        ];
    }

    #[DataProvider('messengerUrls')]
    public function testIsSafeMessengerUrl(string $url, bool $expected): void
    {
        $this->assertSame($expected, isSafeMessengerUrl($url, 255));
    }
}
