# Changelog

Todas las versiones siguen [Semantic Versioning](https://semver.org/lang/es/).

## [Unreleased]

### Añadido

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

[1.0.0]: https://github.com/azulerp/orm/releases/tag/v1.0.0
