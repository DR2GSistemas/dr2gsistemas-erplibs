<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Cache;

use Dr2g\ErpLibs\Cache\CacheManager;
use Dr2g\ErpLibs\Cache\CachesResponses;
use Dr2g\ErpLibs\Routing\Request;
use PHPUnit\Framework\TestCase;

final class CacheController
{
    use CachesResponses;

    public int $calls = 0;

    public function cachedValue(Request $request, int $value = 42): int
    {
        return $this->cached('answer', function () use ($value): int {
            $this->calls++;

            return $value;
        }, tags: ['things'], ttl: 60);
    }

    public function invalidate(): void
    {
        $this->forgetCache(['things']);
    }
}

final class CacheManagerTest extends TestCase
{
    private function manager(string $driver = 'array'): CacheManager
    {
        return new CacheManager(['driver' => $driver, 'ttl' => 60, 'prefix' => 'test:']);
    }

    public function testArrayDriverIsDefault(): void
    {
        $manager = $this->manager('array');

        $manager->store()->put('a', 1);

        self::assertSame(1, $manager->store()->get('a'));
        self::assertTrue($manager->healthy());
        self::assertNull($manager->redis());
    }

    public function testRememberCachesTheCallback(): void
    {
        $manager = $this->manager();
        $calls = 0;
        $callback = static function () use (&$calls): array {
            $calls++;

            return ['n' => $calls];
        };

        self::assertSame(['n' => 1], $manager->remember('k', $callback));
        self::assertSame(['n' => 1], $manager->remember('k', $callback));
        self::assertSame(1, $calls);
    }

    public function testForgetTagsInvalidates(): void
    {
        $manager = $this->manager();
        $manager->remember('a', static fn (): int => 1, tags: ['x']);
        $manager->remember('b', static fn (): int => 2, tags: ['y']);

        $manager->forgetTags(['x']);

        self::assertFalse($manager->store()->has('a'));
        self::assertTrue($manager->store()->has('b'));
    }

    public function testFileDriver(): void
    {
        $path = sys_get_temp_dir() . '/dr2g_cm_' . bin2hex(random_bytes(4));
        $manager = new CacheManager(['driver' => 'file', 'path' => $path]);

        $manager->remember('f', static fn (): string => 'valor');

        self::assertSame('valor', $manager->remember('f', static fn (): string => 'otro'));

        array_map('unlink', glob($path . '/*.cache') ?: []);
        @rmdir($path);
    }

    public function testUnknownDriverFailsLoudly(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->manager('memcached')->store();
    }

    public function testControllerTraitCachesAndInvalidates(): void
    {
        $controller = new CacheController();
        $controller->useCache($this->manager());
        $request = Request::create('GET', '/');

        self::assertSame(42, $controller->cachedValue($request));
        self::assertSame(42, $controller->cachedValue($request, 99));
        self::assertSame(1, $controller->calls);

        $controller->invalidate();

        self::assertSame(99, $controller->cachedValue($request, 99));
        self::assertSame(2, $controller->calls);
    }

    public function testTraitBuildsScopedKeys(): void
    {
        $controller = new CacheController();

        $method = new \ReflectionMethod($controller, 'cacheKey');

        self::assertSame('things:answer', $method->invoke($controller, 'answer', ['things']));
        self::assertSame('cachecontroller:answer', $method->invoke($controller, 'answer'));
    }

    public function testBypassCacheFlag(): void
    {
        $controller = new CacheController();
        $method = new \ReflectionMethod($controller, 'bypassCache');

        self::assertTrue($method->invoke($controller, Request::create('GET', '/', query: ['refresh' => '1'])));
        self::assertFalse($method->invoke($controller, Request::create('GET', '/')));
    }

    public function testFromEnvironmentReadsDriver(): void
    {
        putenv('CACHE_DRIVER=array');
        putenv('CACHE_TTL=120');

        $manager = CacheManager::fromEnvironment();

        self::assertSame('array', $manager->driver());
    }
}
