<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Cache;

use Dr2g\ErpLibs\Cache\ArrayCache;
use Dr2g\ErpLibs\Cache\FileCache;
use PHPUnit\Framework\TestCase;

final class ArrayCacheTest extends TestCase
{
    private float $now = 1_000.0;

    private function cache(): ArrayCache
    {
        return new ArrayCache(fn () => $this->now);
    }

    public function testGetPutForget(): void
    {
        $cache = $this->cache();
        $cache->put('user:1', ['id' => 1]);

        self::assertSame(['id' => 1], $cache->get('user:1'));
        self::assertTrue($cache->has('user:1'));

        $cache->forget('user:1');

        self::assertFalse($cache->has('user:1'));
        self::assertSame('fallback', $cache->get('user:1', 'fallback'));
    }

    public function testExpiredEntriesAreDropped(): void
    {
        $cache = $this->cache();
        $cache->put('tmp', 'x', ttl: 60);

        $this->now += 61;

        self::assertFalse($cache->has('tmp'));
    }

    public function testFlushTagsOnlyRemovesTaggedKeys(): void
    {
        $cache = $this->cache();
        $cache->put('a', 1, tags: ['users']);
        $cache->put('b', 2, tags: ['posts']);
        $cache->put('c', 3, tags: ['users', 'posts']);

        $cache->flushTags(['users']);

        self::assertFalse($cache->has('a'));
        self::assertTrue($cache->has('b'));
        self::assertFalse($cache->has('c'));
    }

    public function testFlushRemovesEverything(): void
    {
        $cache = $this->cache();
        $cache->put('a', 1);
        $cache->put('b', 2);

        $cache->flush();

        self::assertSame([], $cache->all());
    }
}

final class FileCacheTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/azulerp_cache_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("{$this->directory}/*.cache") ?: []);
        @rmdir($this->directory);
    }

    public function testPersistsValuesAcrossInstances(): void
    {
        (new FileCache($this->directory))->put('config', ['debug' => false]);

        $fresh = new FileCache($this->directory);

        self::assertSame(['debug' => false], $fresh->get('config'));
    }

    public function testExpiredEntriesAreIgnored(): void
    {
        $cache = new FileCache($this->directory);
        $cache->put('short', 'x', ttl: -1);

        self::assertFalse($cache->has('short'));
    }

    public function testFlushTags(): void
    {
        $cache = new FileCache($this->directory);
        $cache->put('u1', 1, tags: ['users']);
        $cache->put('p1', 2, tags: ['posts']);

        $cache->flushTags(['users']);

        self::assertFalse($cache->has('u1'));
        self::assertTrue($cache->has('p1'));
    }
}
