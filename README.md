# dr2gsistemas/erplibs

Librería PHP 8.4+ para ERP: ORM por atributos, query builder validado,
migraciones con batches, eventos, validación, serialización, caché, logging,
auditoría y utilidades de resiliencia.

- **PHP** ≥ 8.4 · **Dependencias de runtime**: ninguna · **BD**: MySQL / MariaDB
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
```

Tests de integración (MySQL):

```bash
DR2G_TEST_DSN="mysql:host=127.0.0.1;port=3306;dbname=azulerp" \
DR2G_TEST_PASSWORD=root composer test:all
```

## Estructura

```
src/Core        Connection, QueryBuilder, DatabaseManager, modelo de resiliencia
src/Models      Model + atributos
src/Migrations  DDL, batches, detección desde atributos
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
- [`docs/security.md`](docs/security.md) — checklist OWASP.
- [`docs/performance.md`](docs/performance.md) — benchmarks.
- [`docs/acceptance.md`](docs/acceptance.md) — criterios de aceptación.
- [`CHANGELOG.md`](CHANGELOG.md) — versiones.

## ¿Migrando desde `dan/azulerp-orm`?

El namespace público cambió de `Azulerp\` a `Dr2g\ErpLibs\`. El puente de
compatibilidad en `compat/azulerp_aliases.php` permite que el código antiguo
siga funcionando; el proyecto consumidor lo carga desde su `bootstrap.php`.
Se eliminará en 2.0.
