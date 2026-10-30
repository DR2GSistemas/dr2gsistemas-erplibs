# Changelog

Todas las versiones siguen [Semantic Versioning](https://semver.org/lang/es/).

## [Unreleased]

### Sin cambios

Pendiente de la próxima versión.

## [1.5.0] — 2026-10-30

### Añadido

- **CLI `make:*`**: generadores de componentes para un proyecto ya creado. El
  namespace y la carpeta se leen del `composer.json` del proyecto (subiendo
  directorios desde el actual) o de `--path=`:
  - `make:controller <Nombre>` — `src/Controllers`; con `--resource` genera las
    cinco acciones RESTful y con `--api` un `ApiCrud` en `src/Controllers/Api`.
  - `make:model <Nombre>` — `src/Models`, con la tabla derivada del nombre
    (`User` → `users`, `Category` → `categories`) o `--table=`.
  - `make:view <vista>` — `src/views`; `users.show` crea `users/show.php`, que es
    como `Renderer` resuelve el nombre.
  - `make:route <uri>` — archivo en `src/routes` con la ruta registrada.
  - `make:middleware <Nombre>` — middleware invocable en `src/Http/Middleware`.
  - `make:request <Nombre>` — validación declarativa en `src/Http/Requests`.
  - `make:test <Nombre>` — PHPUnit en `src/tests`, con `--feature`.
- Los comandos nunca sobrescriben un archivo existente sin `--force`.
- Tests: `MakeCommandsTest` cubre cada comando, el rechazo sin `--force`, los
  helpers de nombres y la sintaxis de todo lo generado.

### Corregido

- Scaffold: cuando la librería no estaba en disco, el `composer.json` generado
  incluía un repositorio `path` con `url` vacía, que es inválido y hacía que
  Composer instalara la versión publicada en Packagist en lugar del código local.
  Ahora la entrada `repositories` se omite en ese caso.

## [1.4.0] — 2026-10-30

### Cambiado

- **CLI `app:create` / `new:app`**: todo lo de la aplicación vive ahora en `src/`.
  Además de `Controllers`, `Http`, `Models` y `Support`, se mueven a `src/`
  `routes/`, `views/`, `config/` y `tests/`. Sólo `public/` (docroot) y
  `migrations/` (contrato de `make:migration` / `migrate`) quedan fuera.
  Regla: *lo de la app en `src/`; fuera sólo el docroot y las migraciones*.

### Corregido

- Scaffold: el `TodoCrudTest` generado construía el kernel sin `loadRoutes()`,
  así que todas las peticiones respondían 404. Ahora carga `src/routes`.
- Scaffold: el `KernelTest` generado apuntaba a la carpeta de vistas con una
  profundidad incorrecta y no encontraba `errors.500`. Ahora usa
  `dirname(__DIR__, 4) . '/src/views'`.
- Scaffold: en modo `--api` ya no se genera el `KernelTest` web, que referenciaba
  `{Ns}\Http\Kernel`, una clase inexistente en ese modo.
- Scaffold: el `TodoCrudTest` generado envía el body JSON con
  `Content-Type: application/json`; sin esa cabecera la validación fallaba con 422.
- Scaffold: el `ApiTodoCrudTest` generado separa el query string del path antes de
  `Request::create()`, que no parsea la URL; las pruebas de paginación y búsqueda
  respondían 404.
- Tests: nueva regresión que ejecuta de verdad los tests del proyecto generado,
  en modo web y API, para que estos fallos no vuelvan.

## [1.3.0] — 2026-10-30

### Cambiado

- **CLI `app:create` / `new:app`**: el código PHP del proyecto generado ahora vive
  en `src/` (`src/Controllers`, `src/Http`, `src/Models`, `src/Support`) en lugar
  de `app/`. `routes/`, `views/`, `config/`, `migrations/`, `tests/`, `bin/` y
  `public/` siguen en la raíz del proyecto.
- **Scaffold**: el `composer.json` generado mapea el namespace raíz directamente a
  `src/` (`"Acme\\": "src/"`), sin el segmento `App` intermedio. Las clases pasan
  de `Acme\App\Controllers\HomeController` a `Acme\Controllers\HomeController`.

> **Nota de migración**: los proyectos generados con versiones anteriores conservan
> la estructura `app/` + `{Ns}\App\`; para adoptar la nueva basta con mover el
> código a `src/`, quitar el segmento `App` de los namespaces y actualizar el
> mapeo `autoload.psr-4` del `composer.json`.

## [1.2.0] — 2026-10-30

### Añadido

- **Cache**: `CacheManager` (driver `array|file|redis` por entorno), trait `CachesResponses`
  para controllers, `CachedController`, caché automática en `ApiCrud::index` con
  invalidación en escrituras y `Redis\RedisConnection` (cliente RESP sin `ext-redis`).
  Documentación en `docs/cache.md`.
- **Api**: `Dr2g\ErpLibs\Api\ApiCrud` — CRUD REST especializado (paginación,
  orden, búsqueda, filtros, validación 422, mass-assignment, `Location`, `204`) y
  `Dr2g\ErpLibs\Routing\ApiProblem` para errores JSON.
- **CLI `app:create --api`**: servidor API con `ApiKernel`, middleware JSON,
  health check, controller `ApiCrud` de ejemplo y `docs/api.md`.
- **Routing**: módulo HTTP completo (`Dr2g\ErpLibs\Routing`): `Router`, `Route`,
  `RouteCollection`, `Request`, `Response`, `Controller`, `RouteMatch` y
  excepciones de routing (404/405/409/422). Grupos con middleware aislado,
  recursos RESTful, rutas con nombre y `url()`, ejemplo en `examples/routes.php`
  y guía en `docs/routing.md`.
- **View**: `Renderer` con `render()` y `layout()` para vistas PHP con layout.
- **CLI `new:app`**: genera un proyecto con ruta, controlador, vistas y layout,
  `bin/app`, `public/index.php`, tests y README (`docs/app-scaffold.md`).

### Corregido

- Scaffold: el `ApiKernel` ya no usa `$this` dentro de un closure `static` en el
  manejador de errores (rompía todas las respuestas de error) y declara el
  `use CacheManager`.
- Scaffold: namespace de la aplicación sin el `App\` duplicado (`{Ns}\Http` sobre
  `app/`), que impedía el autoload de los proyectos generados.

## [1.0.0] — 2026-10-30

Primera versión estable del ORM.

### Añadido

- **Core**: `Connection` (PDO, DSN completo, transacciones protegidas, slow-query logging),
  `DatabaseManager` (resolución de `default`, cache de conexiones), `QueryBuilder`
  (`where`, `orWhere`, `whereIn`, `orderBy`, `limit`, `offset`, `select`, `count`, `exists`,
  `toSql`), `ConnectionPool` y `CircuitBreaker`.
- **Models**: `Model` con `#[Table]`, `#[Column]`, `#[PrimaryKey]`, `#[Hidden]`,
  soft deletes, hidratación, `getDirty()`, eventos de ciclo de vida y validación declarativa.
- **Migrations**: `Blueprint`, `SchemaBuilder`, `MigrationManager` (batches transaccionales),
  `MigrationRepository`, `MigrationDetector`, foreign keys fluidas.
- **CLI**: `make:migration`, `migrate`, `migrate:rollback`.
- **Events**: `Dispatcher` con cancelación por `false`.
- **Validation**: `Rule`, `Validator`, reglas extensibles con `Rule::extend()`.
- **Serialization**: `Resource` (con `#[Hidden]` e includes) y `Paginator` (offset y cursor).
- **Cache**: `ArrayCache`, `FileCache`, `RedisCache` con TTL y tags.
- **Log**: `Logger` compatible PSR-3 con sink inyectable.
- **Audit**: `AuditLogger`, `AuditStorageInterface`, `MemoryAuditStorage`, `Gdpr`, `RateLimiter`.
- **Security**: `SQLInjectionGuard` con listas blancas de identificadores, operadores y direcciones.
- **Calidad**: PHPUnit 13, PHPStan nivel 8 sin baseline, PSR-12 con php-cs-fixer, CI en
  GitHub Actions (PHP 8.4/8.5 + MySQL 8.4).
- **Extras**: `benchmarks/run.php`, `examples/run.php`, `docs/adr.md`,
  `docs/performance.md`, `docs/acceptance.md`.

### Corregido

- `Blueprint::index()`/`unique()` declarados dos veces provocaban un error fatal.
- Las migraciones acumulaban DDL pero nunca lo ejecutaban.
- `MigrationManager` derivaba la clase desde el nombre de archivo (con timestamp) y usaba rutas relativas.
- `Blueprint::foreignId()` desapareció al introducir `foreignIdFor()`, rompiendo la API de FK.
- Cada migración generaba su propio batch haciendo el rollback inútil.
- `DatabaseManager::getConnection('default')` no resolvía `$config['default']`.
- `SQLInjectionGuard::sanitize()` corrompía datos con `htmlspecialchars`; se eliminó.
- `ValidationException` fallaba con `implode()` sobre errores multidimensionales.
- `Model::hydrate()` no inicializaba las propiedades tipadas declaradas.

[1.2.0]: https://github.com/DR2GSistemas/dr2gsistemas-erplibs/releases/tag/v1.2.0
[1.0.0]: https://github.com/DR2GSistemas/dr2gsistemas-erplibs/releases/tag/v1.0.0
