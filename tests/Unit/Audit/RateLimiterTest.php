<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Audit;

use Dr2g\ErpLibs\Audit\RateLimiter;
use Dr2g\ErpLibs\Cache\ArrayCache;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    private int $now = 1_700_000_000;

    private function limiter(): RateLimiter
    {
        return new RateLimiter(new ArrayCache(), function (): int {
            return $this->now;
        });
    }

    public function testAllowsUpToTheLimitThenBlocks(): void
    {
        $limiter = $this->limiter();

        self::assertTrue($limiter->attempt('login:1', 2, 60)['allowed']);
        self::assertSame(0, $limiter->attempt('login:1', 2, 60)['remaining']);
        self::assertFalse($limiter->attempt('login:1', 2, 60)['allowed']);
    }

    public function testWindowResetsRetryAfter(): void
    {
        $limiter = $this->limiter();
        $limiter->attempt('login:1', 1, 60);
        $blocked = $limiter->attempt('login:1', 1, 60);

        self::assertFalse($blocked['allowed']);
        self::assertGreaterThanOrEqual(0, $blocked['retry_after']);
        self::assertLessThanOrEqual(60, $blocked['retry_after']);

        $this->now += 60;

        self::assertTrue($limiter->attempt('login:1', 1, 60)['allowed']);
    }

    public function testKeysAreIndependent(): void
    {
        $limiter = $this->limiter();
        $limiter->attempt('a', 1, 60);

        self::assertFalse($limiter->attempt('a', 1, 60)['allowed']);
        self::assertTrue($limiter->attempt('b', 1, 60)['allowed']);
    }
}
