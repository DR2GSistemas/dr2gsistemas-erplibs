# Caché — `Dr2g\ErpLibs\Cache`

Sistema de caché con tres drivers intercambiables y el **mismo contrato** para
controllers web, `ApiCrud` y el resto del ORM. El driver **Redis no requiere
`ext-redis`**: incluye un cliente RESP propio sobre socket.

## Configuración (`.env`)

```ini
CACHE_DRIVER=redis          # array | file | redis
CACHE_PREFIX=dr2g:          # namespace de claves
CACHE_TTL=300               # TTL por defecto en segundos
CACHE_PATH=storage/cache    # solo driver file

REDIS_DSN=tcp://127.0.0.1:6379
REDIS_PASSWORD=
REDIS_DB=0

API_CACHE_TTL=60            # caché del listado del API (0 = desactivada)
```

## Uso en un controller

```php
use Dr2g\ErpLibs\Cache\CachesResponses;
use Dr2g\ErpLibs\Routing\Request;

class PostController
{
    use CachesResponses;

    public function index(Request $request): mixed
    {
        if ($this->bypassCache($request)) {   // ?refresh=1
            $this->forgetCache(['posts']);
        }

        return $this->cached(
            'list',
            static fn (): array => Post::all(),
            tags: ['posts'],
            ttl: 60,
        );
    }

    public function store(Request $request): mixed
    {
        $post = Post::create($request->input());
        $this->forgetCache(['posts']);        // invalida el listado

        return $post;
    }
}
```

También puedes extender `Dr2g\ErpLibs\Routing\CachedController` (ya incluye el trait).

El router inyecta el `CacheManager` del kernel en el controller si implementa
`useCache()`, así todos comparten la misma conexión Redis.

## Uso directo

```php
use Dr2g\ErpLibs\Cache\CacheManager;

$cache = CacheManager::fromEnvironment();          // o new CacheManager([...])

$value = $cache->remember('users.count', fn () => User::query()->count(), 300, ['users']);
$cache->forgetTags(['users']);
$cache->healthy();                                  // PING en redis
$cache->close();
```

## `ApiCrud`

`ApiCrud` cachea automáticamente el listado (`index`) por query string:

- clave `api.<Recurso>:index:<hash de la query>`
- tag `api.<Recurso>`
- TTL `API_CACHE_TTL`
- `GET /api/v1/todos?refresh=1` evita/limpiar la caché
- `store`, `update` y `destroy` invalidan el tag del recurso

## Cliente Redis sin extensión

```php
use Dr2g\ErpLibs\Cache\Redis\RedisConnection;

$redis = RedisConnection::connect('tcp://127.0.0.1:6379', 'password', timeout: 1.0);
$redis->setex('k', 60, 'v');
$redis->get('k');
$redis->ping();
$redis->close();
```

`RedisCache` acepta tanto ese cliente como un `\Redis` de `ext-redis`
(composición, sin acoplar el paquete a la extensión).

## Invalidación por tags

`put($key, $value, $ttl, ['posts'])` registra la clave en el set
`{prefix}tag:posts`. `flushTags(['posts'])` borra todas las claves de ese tag y el
propio set. Es la forma recomendada de invalidar listados tras cambios.

## Pruebas

```bash
composer test                                   # unitarios (array/file/trait)
php vendor/bin/phpunit --testsuite integration  # Redis real (se salta si no hay servidor)
```
