<?php

namespace Dr2g\ErpLibs\Cache;

/**
 * Contract implemented by every cache driver. Tags are a flat list; a store
 * that ignores tags still stores the value.
 */
interface CacheInterface
{
    /**
     * @param list<string> $tags
     */
    public function put(string $key, mixed $value, ?int $ttl = null, array $tags = []): void;

    public function get(string $key, mixed $default = null): mixed;

    public function has(string $key): bool;

    public function forget(string $key): void;

    /**
     * @param list<string> $tags
     */
    public function flushTags(array $tags): void;

    public function flush(): void;
}
