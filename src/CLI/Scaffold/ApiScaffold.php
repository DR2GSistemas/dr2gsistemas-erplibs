<?php

namespace Dr2g\ErpLibs\CLI\Scaffold;

/**
 * Variante API de la aplicación: servidor REST JSON con un CRUD especializado
 * (`Dr2g\ErpLibs\Api\ApiCrud`) sobre el recurso de ejemplo.
 *
 * Generada con `app:create <nombre> --api` (y opcionalmente `--crud=todo`).
 */
final class ApiScaffold
{
    /**
     * @return array<string, string>
     */
    public static function files(string $namespace, bool $withResource = true): array
    {
        $app = $namespace;

        $files = [
            'app/Http/ApiKernel.php' => self::kernel($app),
            'app/Controllers/Api/HealthController.php' => self::healthController($app),
            'views/errors/api-404.php' => self::errorView(),
            'tests/Feature/ApiHealthTest.php' => self::healthTest($app),
            'docs/api.md' => self::documentation($namespace),
        ];

        if ($withResource) {
            $files += [
                'app/Controllers/Api/TodoController.php' => self::resourceController($app),
                'app/Http/Middleware/JsonResponse.php' => self::jsonMiddleware($app),
                'tests/Feature/ApiTodoCrudTest.php' => self::resourceTest($app),
            ];
        }

        return $files;
    }

    public static function kernel(string $app): string
    {
        return <<<PHP
<?php

namespace {$app}\\Http;

use Dr2g\\ErpLibs\\Routing\\ApiProblem;
use Dr2g\\ErpLibs\\Routing\\Exception\\NotFoundException;
use Dr2g\\ErpLibs\\Routing\\Exception\\RoutingException;
use Dr2g\\ErpLibs\\Routing\\Request;
use Dr2g\\ErpLibs\\Routing\\Response;
use Dr2g\\ErpLibs\\Routing\\Router;

/**
 * Kernel de API: sólo JSON, sin vistas. Los errores se serializan con
 * `ApiProblem` y llevan cabecera `X-Api-Version`.
 */
class ApiKernel
{
    private const VERSION = 'v1';

    private Router \$router;

    /**
     * @param array<string, mixed> \$config
     */
    public function __construct(private readonly array \$config = [])
    {
        \$this->router = new Router();

        // Trazabilidad y CORS básico. El resultado del handler puede ser un array
        // (aún no es Response), así que se normaliza con Response::json().
        \$this->router->use(static function (Request \$request, callable \$next): Response {
            \$result = \$next(\$request);

            \$response = \$result instanceof Response
                ? \$result
                : (is_array(\$result) ? Response::json(\$result) : Response::text((string) \$result));

            return \$response
                ->withHeader('X-Api-Version', self::VERSION)
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        });

        \$this->router->setErrorHandler(static function (\\Throwable \$error, Request \$request): Response {
            if (\$error instanceof NotFoundException) {
                \$allowed = \$error->getAllowedMethods();

                return \$allowed === []
                    ? ApiProblem::notFound('Endpoint not found: ' . \$request->method . ' ' . \$request->path)
                    : ApiProblem::methodNotAllowed(\$allowed);
            }

            if (\$error instanceof RoutingException) {
                return ApiProblem::make(\$error->statusCode, 'routing_error', \$error->getMessage());
            }

            if (\$error instanceof \\Dr2g\\ErpLibs\\Exceptions\\ValidationException) {
                return ApiProblem::validation(\$error->errors);
            }

            \$debug = (bool) (\$this->config['debug'] ?? false);

            return \$debug
                ? ApiProblem::internal(\$error->getMessage())
                : ApiProblem::internal();
        });
    }

    public static function boot(string \$root): self
    {
        return require \$root . '/bootstrap.php';
    }

    public function router(): Router
    {
        return \$this->router;
    }

    public function loadRoutes(string \$routesPath): self
    {
        \$kernel = \$this;

        foreach (glob(\$routesPath . '/*.php') ?: [] as \$file) {
            (static function (string \$__file) use (\$kernel): void {
                require \$__file;
            })(\$file);
        }

        return \$this;
    }

    public function handle(?Request \$request = null): Response
    {
        \$request = (\$request ?? Request::fromGlobals())->withAttributes(['kernel' => \$this]);

        return \$this->router->dispatch(\$request);
    }

    /**
     * Servidor embebido: prepara la base de datos de desarrollo (SQLite).
     */
    public function run(): void
    {
        \$this->prepareDevelopmentDatabase();

        \$this->handle()->send();
    }

    private function prepareDevelopmentDatabase(): void
    {
        \$driver = \$this->config['database'] ?? null;

        if (!is_string(\$driver) || !str_starts_with(\$driver, 'sqlite:')) {
            return;
        }

        try {
            \$database = require dirname(__DIR__, 2) . '/config/database.php';
            \$connection = new \Dr2g\ErpLibs\Core\Connection(\$database['connections'][\$database['default']]);

            \$connection->execute(
                'CREATE TABLE IF NOT EXISTS todos (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title VARCHAR(200) NOT NULL,
                    done INTEGER NOT NULL DEFAULT 0,
                    created_at TEXT NULL,
                    updated_at TEXT NULL
                )'
            );
        } catch (\Throwable \$error) {
            error_log('DB init skipped: ' . \$error->getMessage());
        }
    }
}

PHP;
    }

    public static function resourceController(string $app): string
    {
        return <<<PHP
<?php

namespace {$app}\\Controllers\\Api;

use {$app}\\Models\\Todo;
use Dr2g\\ErpLibs\\Api\\ApiCrud;

/**
 * CRUD REST del recurso `todos`.
 *
 * Sólo se declara el contrato: el resto (paginación, filtros, orden,
 * validación, 404/422 y forma JSON) lo aporta {@see ApiCrud}.
 */
final class TodoController extends ApiCrud
{
    protected function model(): string
    {
        return Todo::class;
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'min:3', 'max:200'],
            'done' => ['boolean'],
        ];
    }

    protected function fillable(): array
    {
        return ['title', 'done'];
    }

    protected function sortable(): array
    {
        return ['id', 'title', 'done', 'created_at'];
    }

    protected function filters(): array
    {
        return ['done' => 'done'];
    }

    protected function visible(): array
    {
        return ['id', 'title', 'done', 'created_at', 'updated_at'];
    }

    protected function resourcePath(): string
    {
        return '/api/v1/todos';
    }

    protected function notFoundMessage(): string
    {
        return 'Todo not found.';
    }
}

PHP;
    }

    public static function healthController(string $app): string
    {
        return <<<PHP
<?php

namespace {$app}\\Controllers\\Api;

use Dr2g\\ErpLibs\\Routing\\Request;
use Dr2g\\ErpLibs\\Routing\\Response;

/**
 * Endpoint de salud para balanceadores y monitorización.
 */
final class HealthController
{
    public function show(Request \$request): Response
    {
        return Response::json([
            'data' => [
                'status' => 'ok',
                'app' => getenv('APP_NAME') ?: null,
                'env' => getenv('APP_ENV') ?: 'local',
                'version' => 'v1',
                'time' => date('c'),
            ],
        ]);
    }
}

PHP;
    }

    public static function jsonMiddleware(string $app): string
    {
        return <<<PHP
<?php

namespace {$app}\\Http\\Middleware;

use Dr2g\\ErpLibs\\Routing\\ApiProblem;
use Dr2g\\ErpLibs\\Routing\\Exception\\RoutingException;
use Dr2g\\ErpLibs\\Routing\\Request;

/**
 * Fuerza el contrato HTTP del API en los verbos que escriben:
 *  - `Accept` debe admitir JSON (si no, 406)
 *  - `Content-Type` debe ser application/json (si no, 415)
 *  - el cuerpo debe ser JSON válido (si no, 400)
 */
final class JsonResponse
{
    public function __invoke(Request \$request, callable \$next): mixed
    {
        \$accept = \$request->header('accept') ?? '*/*';

        if (!str_contains(\$accept, 'json') && !str_contains(\$accept, '*/*')) {
            return ApiProblem::make(406, 'not_acceptable', 'This API only responds with JSON.');
        }

        if (in_array(\$request->method, ['POST', 'PUT', 'PATCH'], true)) {
            \$contentType = \$request->header('content-type') ?? '';

            if (!str_contains(\$contentType, 'application/json')) {
                return ApiProblem::make(415, 'unsupported_media_type', 'Content-Type must be application/json.');
            }

            try {
                \$request->json();
            } catch (RoutingException) {
                return ApiProblem::make(400, 'malformed_json', 'The request body is not valid JSON.');
            }
        }

        return \$next(\$request);
    }
}

PHP;
    }

    public static function errorView(): string
    {
        return <<<'HTML'
<?php
/**
 * Fallback HTML para peticiones que no piden JSON.
 *
 * @var int $status
 * @var string $message
 */
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>API — <?= (int) $status ?></title></head>
<body>
<h1>API — Error <?= (int) $status ?></h1>
<p><?= htmlspecialchars($message, ENT_QUOTES) ?></p>
</body>
</html>
HTML;
    }

    public static function healthTest(string $app): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace AppTests;

use {$app}\\Http\\ApiKernel;
use Dr2g\\ErpLibs\\Routing\\Request;
use PHPUnit\\Framework\\TestCase;

final class ApiHealthTest extends TestCase
{
    private function kernel(): ApiKernel
    {
        return (new ApiKernel(['debug' => false]))->loadRoutes(dirname(__DIR__, 2) . '/routes');
    }

    public function testHealthEndpoint(): void
    {
        \$response = \$this->kernel()->handle(Request::create('GET', '/api/v1/health'));

        self::assertSame(200, \$response->status);
        self::assertSame('v1', \$response->headers['X-Api-Version']);
        self::assertSame('application/json; charset=utf-8', \$response->headers['Content-Type']);
    }

    public function testUnknownEndpointUsesJsonError(): void
    {
        \$response = \$this->kernel()->handle(Request::create('GET', '/api/v1/nope'));

        self::assertSame(404, \$response->status);
        self::assertStringContainsString('"code":"not_found"', \$response->body);
    }
}

PHP;
    }

    public static function resourceTest(string $app): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace AppTests;

use {$app}\\Http\\ApiKernel;
use Dr2g\\ErpLibs\\Core\\Connection;
use Dr2g\\ErpLibs\\Core\\DatabaseManager;
use Dr2g\\ErpLibs\\Models\\Model;
use Dr2g\\ErpLibs\\Routing\\Request;
use Dr2g\\ErpLibs\\Routing\\Response;
use PHPUnit\\Framework\\TestCase;

/**
 * Contrato del CRUD REST sobre SQLite en memoria.
 */
final class ApiTodoCrudTest extends TestCase
{
    private ApiKernel \$kernel;

    protected function setUp(): void
    {
        \$connection = new Connection(['dsn' => 'sqlite::memory:']);
        \$connection->execute(
            'CREATE TABLE todos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(200) NOT NULL,
                done INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )'
        );

        \$manager = new DatabaseManager(['default' => 'test', 'connections' => ['test' => []]]);
        (new \\ReflectionProperty(DatabaseManager::class, 'connections'))->setValue(\$manager, ['test' => \$connection]);
        Model::setDbManager(\$manager);
        Model::flushMetadata();

        \$this->kernel = (new ApiKernel(['debug' => false]))->loadRoutes(dirname(__DIR__, 2) . '/routes');
    }

    /**
     * @param array<string, mixed> \$body
     */
    private function call(string \$method, string \$path, array \$body = []): Response
    {
        \$headers = \$body === [] ? [] : ['Content-Type' => 'application/json'];
        \$request = Request::create(\$method, \$path, body: \$body === [] ? '' : (string) json_encode(\$body), headers: \$headers);

        return \$this->kernel->handle(\$request);
    }

    public function testFullCrudContract(): void
    {
        \$created = \$this->call('POST', '/api/v1/todos', ['title' => 'Comprar pan', 'done' => false]);
        self::assertSame(201, \$created->status);
        self::assertStringContainsString('"title":"Comprar pan"', \$created->body);
        self::assertSame('/api/v1/todos/1', \$created->headers['Location'] ?? null);

        \$listed = \$this->call('GET', '/api/v1/todos');
        self::assertSame(200, \$listed->status);
        self::assertStringContainsString('"total":1', \$listed->body);
        self::assertStringContainsString('"per_page":25', \$listed->body);

        \$shown = \$this->call('GET', '/api/v1/todos/1');
        self::assertStringContainsString('"id":1', \$shown->body);

        \$patched = \$this->call('PATCH', '/api/v1/todos/1', ['done' => true]);
        self::assertSame(200, \$patched->status);
        self::assertStringContainsString('"done":true', \$patched->body);

        \$deleted = \$this->call('DELETE', '/api/v1/todos/1');
        self::assertSame(204, \$deleted->status);
        self::assertSame(404, \$this->call('GET', '/api/v1/todos/1')->status);
    }

    public function testValidationErrorUsesApiProblem(): void
    {
        \$response = \$this->call('POST', '/api/v1/todos', ['title' => 'ab']);

        self::assertSame(422, \$response->status);
        self::assertStringContainsString('"code":"validation_failed"', \$response->body);
    }

    public function testMissingResourceReturns404(): void
    {
        \$response = \$this->call('GET', '/api/v1/todos/999');

        self::assertSame(404, \$response->status);
        self::assertStringContainsString('"code":"not_found"', \$response->body);
    }

    public function testPaginationAndSorting(): void
    {
        foreach (['c', 'a', 'b'] as \$title) {
            \$this->call('POST', '/api/v1/todos', ['title' => "Tarea {\$title}"]);
        }

        \$response = \$this->call('GET', '/api/v1/todos?sort=-title&per_page=2&page=1');
        self::assertStringContainsString('"total":3', \$response->body);
        self::assertStringContainsString('"last_page":2', \$response->body);
        self::assertStringContainsString('"sort":"-title"', \$response->body);
    }

    public function testSearchAndFilter(): void
    {
        \$this->call('POST', '/api/v1/todos', ['title' => 'Comprar pan']);
        \$this->call('POST', '/api/v1/todos', ['title' => 'Pagar luz', 'done' => true]);

        self::assertStringContainsString('"total":1', \$this->call('GET', '/api/v1/todos?q=pan')->body);
        self::assertStringContainsString('"total":1', \$this->call('GET', '/api/v1/todos?filter[done]=1')->body);
    }

    public function testMassAssignmentIsBlocked(): void
    {
        \$response = \$this->call('POST', '/api/v1/todos', ['title' => 'Tarea válida', 'id' => 999, 'created_at' => '2000-01-01']);

        self::assertStringContainsString('"id":1', \$response->body);
        self::assertStringNotContainsString('2000-01-01', \$response->body);
    }

    public function testNonJsonContentTypeIsRejected(): void
    {
        \$request = Request::create('POST', '/api/v1/todos', body: 'title=hola');
        \$response = \$this->kernel->handle(\$request);

        self::assertSame(415, \$response->status);
        self::assertStringContainsString('"code":"unsupported_media_type"', \$response->body);
    }
}

PHP;
    }

    public static function routes(string $namespace, bool $withResource = true): string
    {
        $app = $namespace;
        $resource = '';

        if ($withResource) {
            $resource = <<<PHP

            // CRUD REST del recurso de ejemplo (ApiCrud).
            \$kernel->router()->get('/api/v1/todos', '{$app}\\Controllers\\Api\\TodoController@index');
            \$kernel->router()->post('/api/v1/todos', '{$app}\\Controllers\\Api\\TodoController@store');
            \$kernel->router()->get('/api/v1/todos/{id}', '{$app}\\Controllers\\Api\\TodoController@show');
            \$kernel->router()->put('/api/v1/todos/{id}', '{$app}\\Controllers\\Api\\TodoController@update');
            \$kernel->router()->patch('/api/v1/todos/{id}', '{$app}\\Controllers\\Api\\TodoController@update');
            \$kernel->router()->delete('/api/v1/todos/{id}', '{$app}\\Controllers\\Api\\TodoController@destroy');

            PHP;
        }

        return <<<PHP
<?php

/*
 * Rutas del API. El middleware JsonResponse exige Accept/Content-Type JSON.
 *
 * @var {$app}\\Http\\ApiKernel \$kernel
 */

\$kernel->router()->use(new {$app}\\Http\\Middleware\\JsonResponse());

\$kernel->router()->get('/api/v1/health', '{$app}\\Controllers\\Api\\HealthController@show');{$resource}
PHP;
    }

    public static function documentation(string $namespace): string
    {
        return <<<MD
# API — {$namespace}

Servidor REST JSON generado por `app:create --api`.

## Arranque

```bash
composer install
php bin/app app:serve --port 8000
curl http://localhost:8000/api/v1/health
```

## Convenciones

- **Base**: `/api/v1`
- **Formato**: JSON con envoltorio `data` y `meta`
- **Errores**: `{ "error": { "status", "code", "message", "details? } }`
- **Cabeceras**: `X-Api-Version: v1`, `Content-Type: application/json`
- Escrituras requieren `Content-Type: application/json` (si no: `415`).

## Endpoints del recurso `todos`

| Método | Ruta | Acción | Respuesta |
| --- | --- | --- | --- |
| GET | `/api/v1/todos` | index | `200` + `meta` de paginación |
| POST | `/api/v1/todos` | store | `201` + `Location` |
| GET | `/api/v1/todos/{id}` | show | `200` |
| PUT/PATCH | `/api/v1/todos/{id}` | update (parcial) | `200` |
| DELETE | `/api/v1/todos/{id}` | destroy | `204` |
| GET | `/api/v1/health` | health | `200` |

### Listado

```
GET /api/v1/todos?page=2&per_page=50&sort=-created_at&q=texto&filter[done]=1
```

| Parámetro | Descripción |
| --- | --- |
| `page` | página (≥1) |
| `per_page` | tamaño (1–100, por defecto 25) |
| `sort` | columna; prefijo `-` para descendente |
| `q` | búsqueda LIKE en las columnas `searchable()` |
| `filter[campo]` | filtro exacto/LIKE declarado en `filters()` |

Respuesta:

```json
{
  "data": [{ "id": 1, "title": "Comprar pan", "done": false }],
  "meta": { "page": 1, "per_page": 25, "total": 1, "last_page": 1, "sort": null }
}
```

### Errores

| Código | `code` | Cuándo |
| --- | --- | --- |
| `400` | `malformed_json` | cuerpo JSON inválido |
| `404` | `not_found` | recurso o endpoint inexistente |
| `405` | `method_not_allowed` | verbo no soportado (con `Allow`) |
| `406` | `not_acceptable` | `Accept` no admite JSON |
| `415` | `unsupported_media_type` | falta `Content-Type: application/json` |
| `422` | `validation_failed` | reglas de validación |
| `500` | `internal_error` | error no controlado |

## Extender el CRUD

`app/Controllers/Api/TodoController.php` extiende `Dr2g\\ErpLibs\\Api\\ApiCrud`
y sólo declara `model()`, `rules()`, `fillable()`, `sortable()`, `filters()`,
`visible()` y las rutas base. Para un recurso nuevo, copia el controlador y
añade sus rutas en `routes/api.php`.

MD;
    }
}
