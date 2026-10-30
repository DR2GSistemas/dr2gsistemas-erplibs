<?php

namespace Dr2g\ErpLibs\Cache;

/**
 * In-process cache with TTL and tag invalidation. Base class for stores that
 * only differ in how they persist entries.
 */
class ArrayCache implements CacheInterface
{
    /** @var array<string, array{value: mixed, expires: ?float, tags: list<string>}> */
    protected array $entries = [];

    public function __construct(private ?\Closure $clock = null)
    {
    }

    public function put(string $key, mixed $value, ?int $ttl = null, array $tags = []): void
    {
        $this->entries[$key] = [
            'value' => $value,
            'expires' => $ttl === null ? null : $this->time() + $ttl,
            'tags' => $tags,
        ];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->entries[$key]['value'];
    }

    public function has(string $key): bool
    {
        if (!isset($this->entries[$key])) {
            return false;
        }

        $expires = $this->entries[$key]['expires'];

        if ($expires !== null && $expires <= $this->time()) {
            unset($this->entries[$key]);
            return false;
        }

        return true;
    }

    public function forget(string $key): void
    {
        unset($this->entries[$key]);
    }

    public function flushTags(array $tags): void
    {
        foreach ($this->entries as $key => $entry) {
            if (array_intersect($entry['tags'], $tags) !== []) {
                unset($this->entries[$key]);
            }
        }
    }

    public function flush(): void
    {
        $this->entries = [];
    }

    protected function time(): float
    {
        return $this->clock === null ? microtime(true) : ($this->clock)();
    }

    /**
     * @return array<string, array{value: mixed, expires: ?float, tags: list<string>}>
     */
    public function all(): array
    {
        return $this->entries;
    }
}
