<?php

namespace Dr2g\ErpLibs\Cache;

/**
 * Integración de caché en controllers:
 *
 *   class PostController
 *   {
 *       use CachesResponses;
 *
 *       public function index(Request $request)
 *       {
 *           return $this->cached('posts:index', fn () => Post::all(), tags: ['posts'], ttl: 60);
 *       }
 *
 *       public function store(Request $request)
 *       {
 *           $post = Post::create($request->input());
 *           $this->forgetCache(['posts']);           // invalida el listado
 *
 *           return Response::json(['data' => $post->toArray()], 201);
 *       }
 *   }
 *
 * El `CacheManager` se inyecta con `useCache()`; si no se inyecta, se crea uno
 * a partir del entorno (`CACHE_DRIVER`, `REDIS_DSN`, …).
 */
trait CachesResponses
{
    private ?CacheManager $cache = null;

    public function useCache(CacheManager $cache): static
    {
        $this->cache = $cache;

        return $this;
    }

    protected function cacheManager(): CacheManager
    {
        return $this->cache ??= CacheManager::fromEnvironment();
    }

    /**
     * Recuerda el resultado de `$callback` durante `$ttl` segundos.
     *
     * @template T
     * @param callable(): T $callback
     * @param list<string> $tags
     * @return T
     */
    protected function cached(string $key, callable $callback, array $tags = [], ?int $ttl = null): mixed
    {
        $manager = $this->cacheManager();
        $key = $this->cacheKey($key, $tags);

        /** @var T $result */
        $result = $manager->remember($key, $callback, $ttl, $tags);

        return $result;
    }

    /**
     * @param list<string> $tags
     */
    protected function forgetCache(array $tags = [], ?string $key = null): void
    {
        $manager = $this->cacheManager();

        if ($key !== null) {
            $manager->forget($this->cacheKey($key, $tags));
        }

        if ($tags !== []) {
            $manager->forgetTags($tags);
        }
    }

    /**
     * Prefija la clave con el namespace de la instancia para evitar colisiones
     * entre controladores. El prefijo global lo pone `CacheManager`.
     *
     * @param list<string> $tags
     */
    protected function cacheKey(string $key, array $tags = []): string
    {
        $namespace = $tags[0] ?? strtolower((new \ReflectionClass(static::class))->getShortName());

        return preg_replace('/[^a-z0-9:_-]/i', '-', "{$namespace}:{$key}") ?: $key;
    }

    /**
     * ¿Debe saltarse la caché? (`?refresh=1` en la request, útil en pruebas)
     */
    protected function bypassCache(?\Dr2g\ErpLibs\Routing\Request $request = null): bool
    {
        if ($request === null) {
            return false;
        }

        $refresh = $request->query['refresh'] ?? null;

        return in_array($refresh, ['1', 'true', 'yes'], true);
    }
}
