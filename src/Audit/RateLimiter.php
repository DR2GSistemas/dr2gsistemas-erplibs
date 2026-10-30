<?php

namespace Dr2g\ErpLibs\Audit;

use Dr2g\ErpLibs\Cache\CacheInterface;

/**
 * Fixed-window rate limiter backed by any cache driver.
 */
final class RateLimiter
{
    private readonly ?\Closure $clock;

    /**
     * @param callable(): int $clock returns the epoch in seconds
     */
    public function __construct(
        private readonly CacheInterface $cache,
        ?callable $clock = null
    ) {
        $this->clock = $clock === null ? null : \Closure::fromCallable($clock);
    }

    /**
     * @return array{allowed: bool, remaining: int, retry_after: int}
     */
    public function attempt(string $key, int $maxAttempts, int $windowSeconds): array
    {
        $windowSeconds = max(1, $windowSeconds);
        $now = $this->clock === null ? time() : ($this->clock)();
        $windowStart = intdiv($now, $windowSeconds) * $windowSeconds;
        $bucket = "rate:{$key}:{$windowStart}";

        $used = (int) $this->cache->get($bucket, 0);

        if ($used >= $maxAttempts) {
            return [
                'allowed' => false,
                'remaining' => 0,
                'retry_after' => $windowStart + $windowSeconds - $now,
            ];
        }

        $this->cache->put($bucket, $used + 1, $windowSeconds + 1);

        return [
            'allowed' => true,
            'remaining' => $maxAttempts - $used - 1,
            'retry_after' => 0,
        ];
    }
}
