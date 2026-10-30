<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Integration;

use Dr2g\ErpLibs\Cache\CacheManager;
use Dr2g\ErpLibs\Cache\Redis\RedisConnection;
use PHPUnit\Framework\TestCase;

/**
 * Requiere un Redis escuchando en REDIS_DSN (por defecto 127.0.0.1:6379).
 * Se salta automáticamente si no hay servidor.
 *
 *   REDIS_DSN=tcp://127.0.0.1:6379 php vendor/bin/phpunit --testsuite integration
 */
final class RedisCacheIntegrationTest extends TestCase
{
    private ?RedisConnection $client = null;

    private string $prefix;

    protected function setUp(): void
    {
        $dsn = getenv('REDIS_DSN') ?: 'tcp://127.0.0.1:6379';

        try {
            $this->client = RedisConnection::connect($dsn, getenv('REDIS_PASSWORD') ?: null, 0.5);
        } catch (\Throwable) {
            self::markTestSkipped('Redis no disponible en ' . $dsn);
        }

        $this->prefix = 'dr2gtest:' . bin2hex(random_bytes(4)) . ':';
    }

    protected function tearDown(): void
    {
        if ($this->client === null) {
            return;
        }

        foreach ($this->client->keys($this->prefix . '*') as $key) {
            $this->client->del($key);
        }

        $this->client->close();
    }

    public function testPing(): void
    {
        self::assertTrue($this->client->ping());
    }

    public function testSetGetExpireDelete(): void
    {
        $this->client->set($this->prefix . 'a', 'uno');
        self::assertSame('uno', $this->client->get($this->prefix . 'a'));

        $this->client->setex($this->prefix . 'b', 30, 'dos');
        self::assertSame(1, (int) $this->client->exists($this->prefix . 'b'));

        $this->client->del($this->prefix . 'a');
        self::assertNull($this->client->get($this->prefix . 'a'));
    }

    public function testCacheManagerWithRedisDriver(): void
    {
        putenv('CACHE_DRIVER=redis');
        putenv('REDIS_DSN=' . ($this->client->dsn));
        putenv('CACHE_PREFIX=' . $this->prefix);

        $manager = CacheManager::fromEnvironment();

        self::assertSame('redis', $manager->driver());
        self::assertTrue($manager->healthy());

        $calls = 0;
        $callback = static function () use (&$calls): array {
            $calls++;

            return ['n' => $calls];
        };

        self::assertSame(['n' => 1], $manager->remember('users', $callback, ttl: 30, tags: ['users']));
        self::assertSame(['n' => 1], $manager->remember('users', $callback, ttl: 30, tags: ['users']));

        $manager->forgetTags(['users']);
        self::assertFalse($manager->store()->has('users'));

        $manager->close();
    }

    public function testTagInvalidationAcrossKeys(): void
    {
        putenv('CACHE_DRIVER=redis');
        putenv('REDIS_DSN=' . ($this->client->dsn));
        putenv('CACHE_PREFIX=' . $this->prefix);

        $manager = CacheManager::fromEnvironment();
        $manager->remember('posts:1', static fn (): string => 'a', tags: ['posts']);
        $manager->remember('posts:2', static fn (): string => 'b', tags: ['posts']);
        $manager->remember('users:1', static fn (): string => 'c', tags: ['users']);

        $manager->forgetTags(['posts']);

        self::assertFalse($manager->store()->has('posts:1'));
        self::assertFalse($manager->store()->has('posts:2'));
        self::assertTrue($manager->store()->has('users:1'));

        $manager->close();
    }
}
