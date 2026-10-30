# Routing — `Dr2g\ErpLibs\Routing`

Router HTTP para aplicaciones ERP: rutas con parámetros, grupos con middleware,
recursos RESTful y despacho que **siempre** devuelve una `Response`.

## Rutas

```php
use Dr2g\ErpLibs\Routing\{Request, Response, Router};

$router = new Router();

$router->get('/', fn () => 'home');
$router->get('/users/{id}', fn (Request $request) => ['id' => $request->param('id')]);
$router->post('/users', fn (Request $request) => Response::json(User::create($request->json())->toArray(), 201));
$router->get('/posts/{slug?}', fn () => 'opcional');
```

| Patrón | Casa |
| --- | --- |
| `/` | solo `/` |
| `/users` | `/users`, `/users/`, `users` |
| `/users/{id}` | `/users/42` (sin barra final adicional) |
| `/posts/{slug?}` | `/posts` y `/posts/hola-mundo` |

Reglas:

- Un `{param}` debe ocupar el segmento completo (`/users/id-{id}` → excepción).
- Los segmentos opcionales solo pueden ir al final.
- La especificidad decide: `/users/new` gana a `/users/{id}`.
- Los valores se decodifican (`%20` → espacio).

## Respuestas

| Retorno del handler | Respuesta |
| --- | --- |
| `Response` | tal cual |
| `array` | JSON 200 |
| `string` | texto 200 |
| `null` / `true` | 204 |
| `false` | 404 |

```php
Response::json($data, 201);
Response::text('ok');
Response::redirect('/login');
Response::empty(204)->withHeader('X-Trace', 'abc');
```

## Grupos y middleware

```php
$router->use(static function (Request $request, callable $next) {
    $response = $next($request);           // el middleware debe devolver Response

    return $response->withHeader('X-Time', (string) microtime(true));
});

$router->group('/api', static function ($routes): void {
    $routes->get('/health', static fn () => ['status' => 'ok']);
}, [AuthMiddleware::class]);
```

El orden de ejecución es: middleware global → grupo (padre → hijo) → handler.
Un middleware puede cortocircuitar devolviendo `Response` sin llamar a `$next`.
Cada ruta guarda el middleware de su grupo, así que dos grupos no se mezclan.

## Recursos RESTful

```php
$router->group('/api', static function ($routes): void {
    $routes->resource('/users', UserController::class);
});
```

| Verbo | Patrón | Acción |
| --- | --- | --- |
| GET | `/users` | `index` |
| GET | `/users/{id}` | `show` |
| POST | `/users` | `store` |
| PUT | `/users/{id}` | `update` |
| DELETE | `/users/{id}` | `destroy` |

`Controller` devuelve 501 en las acciones no implementadas, de modo que el 501
es el señalizador explícito de "pendiente", no un error silencioso.

## Handlers

```php
$router->get('/a', fn (Request $request) => 'closure');
$router->get('/b', [new MyController(), 'index']);     // instancia
$router->get('/c', MyController::class . '@index');    // clase@método
```

## Errores

| Excepción | HTTP |
| --- | --- |
| `Route not found` (no hay ruta) | 404 |
| `NotFoundException` con métodos | 405 + cabecera `Allow` |
| `ConflictException` | 409 |
| `ValidationException` / `UnprocessableEntityException` | 422 + `errors` |
| Cualquier otra | 500 |

```php
$router->setErrorHandler(static fn (\Throwable $e, Request $r) =>
    Response::json(['message' => $e->getMessage()], 500));
```

## URL de rutas con nombre

```php
$route = $router->get('/users/{id}/posts/{post}', $handler);
echo $route->url(['id' => 42, 'post' => 7]);   // /users/42/posts/7
```

## Ejecutar en producción

```php
$router->run();   // usa $_SERVER/$_GET/php://input y envía la respuesta
```

En un front controller (`public/index.php`) basta con registrar las rutas y
llamar a `run()`.

## Ejemplo ejecutable

```bash
php examples/routes.php
```
