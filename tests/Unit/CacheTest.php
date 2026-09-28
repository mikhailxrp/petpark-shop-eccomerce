<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CacheTest extends TestCase
{
    private string $namespace = 'test-namespace-phpunit';
    private string $key = 'test-key';

    protected function tearDown(): void
    {
        $path = cachePath($this->namespace, $this->key);
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function testMissReturnsNullWhenNothingCached(): void
    {
        $this->assertNull(cacheGet($this->namespace, $this->key, 3600));
    }

    public function testHitReturnsStoredContentWithinTtl(): void
    {
        cachePut($this->namespace, $this->key, '<xml>fresh</xml>');

        $this->assertSame('<xml>fresh</xml>', cacheGet($this->namespace, $this->key, 3600));
    }

    public function testExpiredEntryReturnsNull(): void
    {
        cachePut($this->namespace, $this->key, '<xml>stale</xml>');
        touch(cachePath($this->namespace, $this->key), time() - 100);

        $this->assertNull(cacheGet($this->namespace, $this->key, 60));
    }

    public function testOverwritesPreviousValue(): void
    {
        cachePut($this->namespace, $this->key, '<xml>old</xml>');
        cachePut($this->namespace, $this->key, '<xml>new</xml>');

        $this->assertSame('<xml>new</xml>', cacheGet($this->namespace, $this->key, 3600));
    }
}
