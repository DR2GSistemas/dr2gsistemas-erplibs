# dr2gsistemas/erplibs

[![PHP](https://img.shields.io/badge/php-%E2%89%A58.4-777bb4)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-156%20passing-brightgreen)](#desarrollo-de-la-libreria)

Librería PHP 8.4+ para ERP: ORM por atributos, query builder validado,
migraciones con batches, eventos, validación, serialización, caché, logging,
auditoría, router HTTP y servidor API con CRUD especializado.

- **PHP** ≥ 8.4 · **Dependencias de runtime**: ninguna · **BD**: SQLite, MySQL, MariaDB, PostgreSQL
- **Namespace**: `Dr2g\ErpLibs\`
- **Paquete**: `dr2gsistemas/erplibs`

## Instalación

Desde Packagist o VCS:

```bash
composer require dr2gsistemas/erplibs
```

En un monorepo, como repositorio de tipo `path`:

```json
{
    "repositories": [
        { "type": "path", "url": "libs/dr2gsistemas_erplibs", "options": { "symlink": true } }
    ],
    "require": { "dr2gsistemas/erplibs": "*" }
}
```

## Arranque

```php
use Dr2g\ErpLibs\Core\DatabaseManager;
use Dr2g\ErpLibs\Models\Model;

$config = require 'config/database.php';   // estructura de config/database.php del paquete

Model::setDbManager(new DatabaseManager($config));
```

## Modelo

```php
use Dr2g\ErpLibs\Models\Attributes\{Column, Hidden, Table};
use Dr2g\ErpLibs\Models\Model;

#[Table(name: 'users')]
class User extends Model
{
    #[Column(type: 'VARCHAR(255)', validation: ['required', 'max:255'])]
    public string $name;

    #[Column(type: 'VARCHAR(190)', validation: ['required', 'email'])]
    public string $email;

    #[Hidden]
    #[Column(type: 'VARCHAR(255)')]
    public string $password_hash;
}

$user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password_hash' => 'x']);
$user->fill(['name' => 'Grace'])->save();

User::find(1);
User::query()->where('email', 'LIKE', '%@example.com')->orderBy('name')->limit(10)->get();
```

## API por subsistema

| Subsistema | Clases | Documento |
| --- | --- | --- |
| Core | `Connection`, `DatabaseManager`, `QueryBuilder`, `ConnectionPool`, `CircuitBreaker` | [README](README.md) §Caché/§Logging |
| Models | `Model`, atributos `Table/Column/PrimaryKey/Hidden/Validation` | §Modelo |
| Routing | `Router`, `Route`, `RouteCollection`, `Request`, `Response`, `Controller` | [docs/routing.md](docs/routing.md) |
| View | `Renderer` (`render`, `layout`) | [docs/app-scaffold.md](docs/app-scaffold.md) |
| Migrations | `Migration`, `Blueprint`, `SchemaBuilder`, `MigrationManager`, `MigrationRepository`, `MigrationDetector` | §Migraciones |
| Events | `Dispatcher` (`saving`, `creating`, `created`, `updating`, `updated`, `deleting`, `deleted`) | §Eventos |
| Validation | `Validator`, `Rule` (`required`, `string`, `integer`, `numeric`, `email`, `min`, `max`, `boolean`, `Rule::extend`) | §Validación |
| Serialization | `Resource` (respeta `#[Hidden]`), `Paginator` (offset y cursor) | §Serialización |
| Cache | `CacheInterface`, `ArrayCache`, `FileCache`, `RedisCache`, `RedisClientInterface` | §Caché |
| Log | `Logger` (PSR-3, sink inyectable) | §Logging |
| Audit | `AuditLogger`, `AuditStorageInterface`, `MemoryAuditStorage`, `Gdpr`, `RateLimiter` | §Audit |
| Testing | `Testing\FakeConnection`, `Testing\ModelFactory` | §Testing |

## Testing en consumidores

```php
use Dr2g\ErpLibs\Testing\ModelFactory;

$connection = ModelFactory::withFakeConnection(['last_insert_id' => 10]);

$user = User::create(['name' => 'Ada', 'email' => 'a@b.c', 'password_hash' => 'x']);

assert($user->id === 10);
assert($connection->inserts[0]['table'] === 'users');
```

## CLI

```bash
php vendor/bin/erplibs new:app ../mi-erp --namespace="Acme" --name="Mi ERP"
php vendor/bin/erplibs make:migration create_posts_table
php vendor/bin/erplibs migrate
php vendor/bin/erplibs migrate:rollback --steps=1
```

## Desarrollo de la librería

```bash
composer install
composer test        # PHPUnit (suite unit)
composer analyse     # PHPStan nivel 8, sin baseline
composer lint        # PSR-12
composer check       # los tres
composer audit:release  # qué archivos entran en el paquete
```

Tests de integración (MySQL):

```bash
DR2G_TEST_DSN="mysql:host=127.0.0.1;port=3306;dbname=dr2g_ci" \
DR2G_TEST_PASSWORD=root composer test:all
```

## Estructura

```
src/Api         ApiCrud (CRUD REST) y utilidades
src/Core        Connection, QueryBuilder, DatabaseManager, modelo de resiliencia
src/Models      Model + atributos
src/Migrations  DDL, batches, detección desde atributos
src/Routing     Router, Route/RouteCollection, Request/Response, Controller
src/View        Renderer de vistas PHP con layout
src/Events      Dispatcher
src/Validation  Rule + Validator
src/Serialization Resource + Paginator
src/Cache       ArrayCache, FileCache, RedisCache
src/Log         Logger PSR-3
src/Audit       Auditoría, GDPR, rate limiting
src/Security    SQLInjectionGuard
src/Testing     Dobles y helpers para consumidores
```

## Documentación

- [`README.md`](README.md) — esta guía (secciones detalladas abajo).
- [`docs/adr.md`](docs/adr.md) — decisiones de arquitectura.
- [`docs/routing.md`](docs/routing.md) — router HTTP.
- [`docs/app-scaffold.md`](docs/app-scaffold.md) — `new:app` y estructura de proyecto.
- [`docs/security.md`](docs/security.md) — checklist OWASP.
- [`docs/performance.md`](docs/performance.md) — benchmarks.
- [`docs/acceptance.md`](docs/acceptance.md) — criterios de aceptación.
- [`CHANGELOG.md`](CHANGELOG.md) — versiones.

## Requisitos y soporte

- PHP ≥ 8.4 (probado en 8.4 y 8.5).
- Extensiones: `pdo` y el driver de la base de datos usada (`pdo_sqlite`, `pdo_mysql`, `pdo_pgsql`).
- `ext-redis` es opcional (solo para `RedisCache`).

## Licencia y soporte

Ver [`LICENSE`](LICENSE) y [`SECURITY.md`](SECURITY.md) para reportar vulnerabilidades.
