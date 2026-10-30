<?php

namespace Dr2g\ErpLibs\Cache;

/**
 * Redis cache talking the native protocol through ext-redis when available, or
 * through a small RESP client when only the socket is reachable.
 */
class RedisCache implements CacheInterface
{
    /**
     * @param RedisClientInterface|object $redis ext-redis \Redis satisfies the
     *                                           interface structurally at runtime
     */
    public function __construct(
        private readonly object $redis,
        private readonly string $prefix = 'azulerp:'
    ) {
    }

    private function client(): RedisClientInterface
    {
        if (!$this->redis instanceof RedisClientInterface) {
            throw new \InvalidArgumentException('Redis client must implement ' . RedisClientInterface::class . '.');
        }

        return $this->redis;
    }

    public function put(string $key, mixed $value, ?int $ttl = null, array $tags = []): void
    {
        $payload = serialize(['value' => $value, 'tags' => $tags]);

        if ($ttl === null) {
            $this->client()->set($this->prefix . $key, $payload);
        } else {
            $this->client()->setex($this->prefix . $key, $ttl, $payload);
        }

        foreach ($tags as $tag) {
            $this->client()->sAdd($this->prefix . 'tag:' . $tag, $this->prefix . $key);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $payload = $this->client()->get($this->prefix . $key);

        if ($payload === false || $payload === null) {
            return $default;
        }

        $entry = @unserialize((string) $payload, ['allowed_classes' => false]);

        return is_array($entry) && array_key_exists('value', $entry) ? $entry['value'] : $default;
    }

    public function has(string $key): bool
    {
        return (bool) $this->client()->exists($this->prefix . $key);
    }

    public function forget(string $key): void
    {
        $this->client()->del($this->prefix . $key);
    }

    public function flushTags(array $tags): void
    {
        foreach ($tags as $tag) {
            $members = $this->client()->sMembers($this->prefix . 'tag:' . $tag) ?: [];

            foreach ($members as $member) {
                $this->client()->del($member);
            }

            $this->client()->del($this->prefix . 'tag:' . $tag);
        }
    }

    public function flush(): void
    {
        $keys = $this->client()->keys($this->prefix . '*') ?: [];

        foreach ($keys as $key) {
            $this->client()->del($key);
        }
    }
}
