<?php

namespace Dr2g\ErpLibs\Cache;

use Dr2g\ErpLibs\Cache\Redis\RedisConnection;

/**
 * Fábrica del sistema de caché: elige el driver según `CACHE_DRIVER`
 * (array|file|redis) y lo expone con la misma interfaz para controllers,
 * modelos y API.
 *
 *   CACHE_DRIVER=redis
 *   REDIS_DSN=tcp://127.0.0.1:6379
 *   REDIS_PASSWORD=…            (opcional)
 *   REDIS_DB=0                  (opcional)
 *   CACHE_PREFIX=dr2g:          (opcional)
 *   CACHE_TTL=300               (segundos por defecto)
 */
final class CacheManager
{
    private ?CacheInterface $store = null;

    private ?RedisConnection $connection = null;

    private int $defaultTtl;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private readonly array $config = [])
    {
        $this->defaultTtl = (int) ($config['ttl'] ?? (getenv('CACHE_TTL') ?: 300));
    }

    /**
     * CacheManager a partir de variables de entorno (o de un archivo .env ya cargado).
     */
    public static function fromEnvironment(): self
    {
        return new self([
            'driver' => getenv('CACHE_DRIVER') ?: 'array',
            'path' => getenv('CACHE_PATH') ?: sys_get_temp_dir() . '/dr2g-cache',
            'prefix' => getenv('CACHE_PREFIX') ?: 'dr2g:',
            'ttl' => (int) (getenv('CACHE_TTL') ?: 300),
            'redis' => [
                'dsn' => getenv('REDIS_DSN') ?: 'tcp://127.0.0.1:6379',
                'password' => getenv('REDIS_PASSWORD') ?: null,
                'database' => getenv('REDIS_DB') ?: null,
            ],
        ]);
    }

    public function driver(): string
    {
        return strtolower((string) ($this->config['driver'] ?? 'array'));
    }

    public function store(): CacheInterface
    {
        return $this->store ??= $this->createStore();
    }

    /**
     * ¿El backend responde? (solo redis lo verifica de verdad).
     */
    public function healthy(): bool
    {
        if ($this->driver() !== 'redis') {
            return true;
        }

        try {
            return $this->connectionOrFail()->ping();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Respuesta lista para un controller: recuerda el resultado de `$callback`
     * durante `$ttl` segundos, o lo devuelve desde cache.
     *
     * @template T
     * @param callable(): T $callback
     * @param list<string> $tags
     * @return T
     */
    public function remember(string $key, callable $callback, ?int $ttl = null, array $tags = []): mixed
    {
        $store = $this->store();

        if ($store->has($key)) {
            /** @var T $cached */
            $cached = $store->get($key);

            return $cached;
        }

        $value = $callback();
        $store->put($key, $value, $ttl ?? $this->defaultTtl, $tags);

        return $value;
    }

    /**
     * Invalida todo lo etiquetado (p. ej. tras un CRUD).
     *
     * @param list<string> $tags
     */
    public function forgetTags(array $tags): void
    {
        $this->store()->flushTags($tags);
    }

    public function forget(string $key): void
    {
        $this->store()->forget($key);
    }

    public function flush(): void
    {
        $this->store()->flush();
    }

    /**
     * Cliente Redis subyacente (null si el driver no es redis).
     */
    public function redis(): ?RedisConnection
    {
        if ($this->driver() !== 'redis') {
            return null;
        }

        return $this->connectionOrFail();
    }

    private function connectionOrFail(): RedisConnection
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        $redis = is_array($this->config['redis'] ?? null) ? $this->config['redis'] : [];
        $password = $redis['password'] ?? null;

        $this->connection = RedisConnection::connect(
            (string) ($redis['dsn'] ?? 'tcp://127.0.0.1:6379'),
            is_string($password) && $password !== '' ? $password : null,
        );

        $database = $redis['database'] ?? null;

        if ($database !== null && $database !== '' && (int) $database !== 0) {
            $this->connection->command('SELECT', [(int) $database]);
        }

        return $this->connection;
    }

    public function close(): void
    {
        $this->connection?->close();
        $this->connection = null;
        $this->store = null;
    }

    private function createStore(): CacheInterface
    {
        $prefix = (string) ($this->config['prefix'] ?? 'dr2g:');

        return match ($this->driver()) {
            'redis' => new RedisCache($this->connectionOrFail(), $prefix),
            'file' => new FileCache((string) ($this->config['path'] ?? sys_get_temp_dir() . '/dr2g-cache')),
            'array', 'memory' => new ArrayCache(),
            default => throw new \InvalidArgumentException(
                'CACHE_DRIVER no soportado: ' . $this->driver() . '. Usa array, file o redis.'
            ),
        };
    }
}
