<?php

namespace Dr2g\ErpLibs\Cache;

/**
 * The subset of ext-redis used by RedisCache; any object with this surface works
 * (ext-redis, a fake, or a wrapper).
 */
interface RedisClientInterface
{
    public function set(string $key, string $value): mixed;

    public function setex(string $key, int $ttl, string $value): mixed;

    public function get(string $key): mixed;

    public function exists(string $key): mixed;

    public function del(string $key): mixed;

    public function sAdd(string $key, string $member): mixed;

    /**
     * @return list<string>
     */
    public function sMembers(string $key): array;

    /**
     * @return list<string>
     */
    public function keys(string $pattern): array;
}
