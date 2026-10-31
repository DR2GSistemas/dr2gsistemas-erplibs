# Changelog

Todas las versiones siguen [Semantic Versioning](https://semver.org/lang/es/).

## [Unreleased]

### Sin cambios

Pendiente de la próxima versión.

## [1.8.1] — 2026-10-30

### Corregido

- **SQLite en proyectos generados**: `migrate` y `migrate:rollback` no cargaban
  `.env` antes de resolver la conexión, lo que podía crear `storage/app.sqlite`
  además del archivo definido por `DB_DATABASE`. Los comandos cargan ahora el
  entorno del proyecto, y el servidor embebido deriva el DSN SQLite de esa misma
  configuración.

## [1.8.0] — 2026-10-30

### Añadido

- **Auth (RBAC)**: infraestructura reutilizable en `src/Auth/`:
  - `Session\Session` sobre `$_SESSION` (cookie `httponly`, `samesite=Lax`,
    `secure` opcional) y `Session\ArraySession` para tests.
  - `Csrf\CsrfTokenManager` con comparación `hash_equals()` y `rotate()`.
  - `Password\Hasher` (`password_hash` / `password_verify` / `needsRehash`).
  - `Gate` (permisos con comodines `users.*` y `*`), `AuthManager`
    (login/logout con regeneración de id de sesión) y la interfaz `Authorizable`.
  - Middleware `Authenticate` (redirige o 401 JSON) y `RequirePermission`
    (403 con vista o JSON).
- **Repositorios N:N**: `Repository::attachMany()`, `syncMany()` y `relatedIds()`
  para tablas pivote, con identificadores validados por `SQLInjectionGuard` y
  valores siempre por binding.
- **`Model::connection()`**: acceso público a la conexión del modelo.
- **CLI `make:auth [--no-register] [--force] [--path=]`**: genera modelos `User`,
  `Role` y `Permission`, repositorios con roles y permisos efectivos, migración de
  las cinco tablas, seeder del administrador (permisos, rol `admin` y usuario desde
  `INSTALL_ADMIN_*`), middleware `StartSession`/`VerifyCsrf`, controladores de
  login/registro, rutas, vistas (login, registro, 403) y tests.
- **CLI `db:seed [Clase] [--path=]`**: ejecuta un seeder del proyecto
  (`AuthSeeder` por defecto).
- **Panel de administración** (`make:auth`): controladores `Admin\UsersController`
  (listado paginado, alta, edición con roles, borrado), `Admin\RolesController`
  (CRUD con asignación de permisos) y `Admin\PermissionsController` (catálogo);
  rutas `/admin/*` protegidas por sesión y permiso; vistas de usuarios, roles,
  permisos, navegación y error 419; test de acceso al panel.
- **Kernel generado**: `withServices()` permite pasar sesión, CSRF, auth y gate a
  los archivos de `src/routes/`; `loadRoutes()` comparte ámbito entre ellos y
  hace `extract()` de esos servicios, de modo que el orden alfabético de carga
  deja de importar. El bootstrap genera el bloque de autenticación sólo si el
  proyecto tiene `make:auth`.

### Corregido

- **`migrate` y `migrate:rollback`** buscaban `config/database.php` (estructura
  anterior a la 1.4.0) y no encontraban la configuración en `src/config/`. Ahora
  aceptan ambas ubicaciones.
- **`migrate`** usaba por defecto el directorio `migrations/` de la librería, así
  que las migraciones de un proyecto generado nunca se ejecutaban.
- **`Connection::getDriver()`** devolvía cadena vacía cuando la conexión sólo
  declaraba `dsn` (caso de SQLite en los proyectos generados): las migraciones
  usaban DDL de MySQL y fallaban. Ahora deduce el motor del DSN.
- Scaffold: `INSTALL_ADMIN_EMAIL` usaba `admin@localhost`, que `FILTER_VALIDATE_EMAIL`
  rechaza; ahora es `admin@example.com`.
- **Sesión en CLI**: `Session::start()` llamaba a `session_name()`/`session_start()`
  también en línea de comandos, lo que emite cabeceras y falla en scripts y
  seeders. Ahora usa una sesión en memoria cuando no hay SAPI web.
- **Kernel generado**: `loadRoutes()` aislaba cada archivo de rutas en un closure,
  así que un archivo no podía usar variables definidas por otro (ni `routes/todo.php`
  las de `web.php`). Ahora comparten ámbito.

## [1.7.0] — 2026-10-30

### Añadido

- **ORM: sellos de tiempo automáticos.** `Model::save()` fija `created_at` (solo
  al crear) y `updated_at` (en cada escritura) cuando el modelo declara esas
  columnas con `#[Column]`. Antes había que asignarlas a mano.
- **`Model::declaresColumn()`**: utilidad pública para saber si el modelo declara
  una columna por atributo.
- **CLI `make:model`**: el modelo generado incluye `created_at`, `updated_at` y
  `deleted_at` (con `#[Column(type: 'DATETIME', nullable: true)]`), de modo que
  los timestamps y el borrado lógico funcionan sin configuración.
- **CLI `make:migration`**: el DDL de `create` incluye también `softDeletes()`
  para que la columna `deleted_at` exista.

### Notas de diseño

- No hay trait `SoftDeletes` en esta librería: el borrado lógico se activa
  declarando la propiedad `deleted_at` con `#[Column]` (`Model::usesSoftDeletes()`).
- Los sellos se aplican **solo** si la propiedad existe y tiene `#[Column]`, así
  que los modelos sobre tablas sin esas columnas no se ven afectados.

## [1.6.1] — 2026-10-30

### Corregido

- **CLI `app:create --force`**: regenerar un proyecto que ya tenía `vendor/`
  reescribía el código con el nuevo namespace pero dejaba el autoloader de
  Composer apuntando al anterior, así que `bootstrap.php` fallaba con
  `Class "App\Http\Kernel" not found`. Ahora, si el proyecto ya tiene
  dependencias instaladas, se ejecuta `composer dump-autoload --no-cache` para
  rehacer `vendor/composer/autoload_*.php`. Si Composer no está disponible, se
  avisa por STDERR sin abortar.

  > Corrección manual equivalente: `composer dump-autoload --no-cache` dentro del
  > proyecto. `--no-cache` es necesario porque Composer reutiliza el autoload
  > guardado en `vendor/composer/installed.json`.

## [1.6.0] — 2026-10-30

### Añadido

- **Subviews**: nuevo `Renderer::subview($view, $data)` y helper `$subview()`
  inyectado en vistas, layouts y subviews. Las subviews se evalúan aisladas, se
  pueden anidar y viven en `src/views/partials/`.
- **CLI `make:subview`**: genera una subview en `src/views/partials`, con
  jerarquía (`users.row` → `partials/users/row.php`).
- **Repositories**: `Dr2g\ErpLibs\Repositories\Repository`, base abstracta con
  `find`, `findOrFail`, `all`, `paginate`, `create`, `update`, `delete` y
  `count`. Devuelve siempre modelos hidratados; `paginate` incluye `items`, `total`,
  `page`, `per_page` y `last_page`, y limita `per_page` a 100.
- **CLI `make:repository`**: genera un repositorio que extiende la base y declara
  el modelo (`--model=` opcional).
- Tests: `SubviewTest` y `RepositoryTest`, más los casos de los dos comandos nuevos.

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
