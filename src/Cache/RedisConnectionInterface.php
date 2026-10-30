<?php

namespace Dr2g\ErpLibs\Cache;

/**
 * Cliente que `RedisCache` puede usar para invalidar por tags y comprobar salud.
 */
interface RedisConnectionInterface extends RedisClientInterface
{
    public function ping(): bool;

    public function close(): void;
}
