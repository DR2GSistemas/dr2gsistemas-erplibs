<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Cache;

use Dr2g\ErpLibs\Cache\RedisCache;
use Dr2g\ErpLibs\Cache\RedisClientInterface;
use PHPUnit\Framework\TestCase;

/**
 * In-memory stand-in for ext-redis: enough surface for RedisCache to be tested
 * without a server or the extension.
 */
final class FakeRedis implements RedisClientInterface
{
    /** @var array<string, string> */
    public array $values = [];
    /** @var array<string, array<string, true>> */
    public array $sets = [];

    public function set(string $key, string $value): bool
    {
        $this->values[$key] = $value;
        return true;
    }

    public function setex(string $key, int $ttl, string $value): bool
    {
        return $this->set($key, $value);
    }

    public function get(string $key): string|false
    {
        return $this->values[$key] ?? false;
    }

    public function exists(string $key): int
    {
        return isset($this->values[$key]) ? 1 : 0;
    }

    public function del(string $key): int
    {
        unset($this->values[$key]);
        return 1;
    }

    public function sAdd(string $key, string $member): int
    {
        $this->sets[$key][$member] = true;
        return 1;
    }

    public function sMembers(string $key): array
    {
        return array_keys($this->sets[$key] ?? []);
    }

    public function keys(string $pattern): array
    {
        $prefix = rtrim($pattern, '*');
        return array_values(array_filter(
            array_keys($this->values),
            static fn (string $key) => str_starts_with($key, $prefix)
        ));
    }
}

final class RedisCacheTest extends TestCase
{
    public function testPutGetForget(): void
    {
        $redis = new FakeRedis();
        $cache = new RedisCache($redis, 't:');

        $cache->put('user:1', ['id' => 1]);

        self::assertSame(['id' => 1], $cache->get('user:1'));
        self::assertTrue($cache->has('user:1'));

        $cache->forget('user:1');

        self::assertFalse($cache->has('user:1'));
    }

    public function testTagFlushDeletesMembers(): void
    {
        $redis = new FakeRedis();
        $cache = new RedisCache($redis, 't:');
        $cache->put('u1', 1, tags: ['users']);
        $cache->put('p1', 2, tags: ['posts']);

        $cache->flushTags(['users']);

        self::assertFalse($cache->has('u1'));
        self::assertTrue($cache->has('p1'));
    }

    public function testFlushRemovesPrefixedKeysOnly(): void
    {
        $redis = new FakeRedis();
        $cache = new RedisCache($redis, 't:');
        $cache->put('a', 1);
        $redis->set('other', 'keep');

        $cache->flush();

        self::assertSame('keep', $redis->values['other']);
        self::assertArrayNotHasKey('t:a', $redis->values);
    }
}
