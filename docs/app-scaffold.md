# Aplicaciones — `app:create` y `new:app`

Genera un proyecto arrancable con **ruta + controlador + vistas** conectados a la
librería y, opcionalmente, un **CRUD de ejemplo** completo.

```bash
# App base (ruta + controller + views)
vendor/bin/erplibs app:create proyect001

# App base + CRUD de todolist de prueba (modelo, migración, controller, vistas, tests)
vendor/bin/erplibs app:create proyect001 --crud=todo

cd proyect001
composer install
php -S localhost:8080 -t public public/index.php
open http://localhost:8080/todos
```

## Variables de entorno: `.env.template`

`app:create` genera **tres** archivos de entorno:

| Archivo | Se versiona | Contenido |
| --- | --- | --- |
| `.env.template` | sí | documentación de todas las variables y sus valores por defecto |
| `.env` | **no** | copia lista para usar: `APP_KEY` y `INSTALL_ADMIN_PASSWORD` generados |
| `.env.local.template` | sí | overrides locales (credenciales reales); copiar a `.env.local` |

Precedencia: entorno del sistema > `.env.local` > `.env` (las variables ya definidas
en el proceso nunca se sobrescriben).

### Secciones del `.env.template`

| Sección | Variables |
| --- | --- |
| Aplicación | `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_PORT`, `APP_TIMEZONE`, `APP_LOCALE`, `APP_KEY` |
| Motor de BD | `DB_CONNECTION` (`sqlite`/`mysql`/`mariadb`/`pgsql`), `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_CHARSET`, `DB_DSN` |
| Datos iniciales | `INSTALL_ADMIN_NAME`, `INSTALL_ADMIN_EMAIL`, `INSTALL_ADMIN_PASSWORD`, `MAIL_FROM`, `MAIL_TRANSPORT`, `LOG_LEVEL` |
| CRUD de prueba | `SEED_TODOS`, `SEED_TODO_TITLE` (sólo con `--crud=todo`) |
| Identidad | `PROJECT_NAMESPACE`, `PROJECT_SLUG` |

### Cómo se usan

```php
// bootstrap.php
DotEnv::load(__DIR__ . '/.env');
DotEnv::load(__DIR__ . '/.env.local');   // gana este último
$config = require 'config/app.php';      // APP_NAME, APP_PORT, APP_DEBUG...
$database = require 'config/database.php'; // DB_CONNECTION, DB_HOST...
```

- `bin/app app:serve` usa **`APP_PORT`** si no se pasa `--port`.
- Con `DB_CONNECTION=sqlite` la base es `DB_DATABASE` (ruta relativa al proyecto)
  y la tabla se crea al arrancar el servidor embebido. `migrate` y
  `migrate:rollback` cargan la configuración de `.env` antes de abrir la base,
  por lo que ambos comandos y el servidor usan el mismo archivo.
- Con `DB_CONNECTION=mysql|mariadb|pgsql` se arma el DSN con los `DB_*`
  (o se usa `DB_DSN` si está definido) y se aplica con `php bin/app migrate`.
- `SEED_TODOS=true` inserta `SEED_TODO_TITLE` la primera vez que se visita `/`.

## Servir la app: `app:serve`

```bash
# desde la raíz del proyecto generado (bin/app ya apunta a este proyecto)
php bin/app app:serve --port 8002
php bin/app app:serve --port=8002

# desde la librería / monorepo, indicando el proyecto
vendor/bin/erplibs app:serve ../proyect001 --port 8002
```

| Opción | Default | Descripción |
| --- | --- | --- |
| `--port=N` / `--port N` | `8000` | puerto (1–65535) |
| `--host=N` / `--host N` | `127.0.0.1` | interfaz (`0.0.0.0` para exponer) |
| `--router=archivo` | `public/index.php` | front controller |
| `<proyecto>` | cwd | raíz de la app |

Comportamiento:

- Valida que exista `public/index.php` antes de arrancar.
- Si el puerto ya está ocupado, falla con un mensaje claro en lugar de dejar el
  servidor a medias.
- Muestra la URL y el comando `php -S` exacto que ejecuta.
- Ctrl+C detiene el servidor (es el mismo proceso).

### Parámetros de ruta en acciones

El router inyecta los parámetros `{id}` por nombre en la acción:

```php
$kernel->router()->get('/todos/{id}', 'Acme\App\Controllers\TodoController@show');

class TodoController
{
    public function show(Request $request, int $id): Response { /* ... */ }
}
```

> Si declaras parámetros extra, no heredes de `Controller` sin sobrescribir la
> firma: `Controller` ya define `show(Request)`. El scaffold CRUD usa una clase
> propia sin herencia.

## Opciones de `app:create`

| Opción | Default | Descripción |
| --- | --- | --- |
| `<nombre>` | — | crea `<nombre>/` (namespace `Proyect001`, `MiErp`…) |
| `--crud=` | — | `todo` añade el CRUD de prueba |
| `--namespace=` | del nombre | raíz PSR-4, p. ej. `Acme\Erp` |
| `--path=` | raíz del monorepo | directorio donde crear el proyecto |
| `--force` | `false` | escribe sobre un directorio no vacío |
| `--dry-run` | `false` | lista lo que crearía sin escribir |

`new:app` es la variante anterior (recibe el directorio completo y no añade CRUD).

### Regenerar un proyecto existente

`app:create <nombre> --force` reescribe el scaffold y, si el proyecto ya tenía
`vendor/`, rehace el autoloader de Composer (`composer dump-autoload --no-cache`).
Sin ese paso, cambiar `--namespace` deja el autoload apuntando al namespace
anterior y `bootstrap.php` falla con `Class "...\Http\Kernel" not found`.

Si tienes un proyecto en ese estado, ejecuta dentro de él:

```bash
composer dump-autoload --no-cache
```

`--no-cache` es necesario: Composer reutiliza el autoload guardado en
`vendor/composer/installed.json`.

## Comandos `make:*` para componentes

Se ejecutan **dentro** del proyecto generado (buscan `composer.json` hacia arriba)
y toman el namespace y la carpeta `src/` de su `autoload.psr-4`. Con `--path=`
se puede apuntar a otro proyecto.

| Comando | Crea | Opciones |
| --- | --- | --- |
| `make:controller <Nombre>` | `src/Controllers/<Nombre>Controller.php` | `--resource`, `--api`, `--action=`, `--view=`, `--force` |
| `make:model <Nombre>` | `src/Models/<Nombre>.php` | `--table=`, `--force` |
| `make:view <vista>` | `src/views/<vista>.php` | `--force` |
| `make:route <uri>` | `src/routes/<nombre>.php` | `--controller=Nombre@accion`, `--method=`, `--file=`, `--force` |
| `make:middleware <Nombre>` | `src/Http/Middleware/<Nombre>.php` | `--force` |
| `make:request <Nombre>` | `src/Http/Requests/<Nombre>Request.php` | `--force` |
| `make:repository <Nombre>` | `src/Repositories/<Nombre>Repository.php` | `--model=`, `--force` |
| `make:subview <nombre>` | `src/views/partials/<nombre>.php` | `--force` |
| `make:auth` | modelos, repositorios, migración, seeder, middleware, controladores, rutas y vistas de autenticación | `--no-register`, `--force` |
| `feature:enable vue` | Vue 3, TypeScript, Composition API y Vite integrados al layout PHP | `--path=` |
| `feature:enable material` | tema CSS Material Design generado, sin dependencia externa | `--path=` |
| `feature:enable primevue` | PrimeVue 4 y preset Aura | `--path=` |
| `db:seed [Clase]` | ejecuta un seeder (`AuthSeeder` por defecto) | `--path=` |
| `make:test <Nombre>` | `src/tests/Unit/<Nombre>Test.php` | `--feature`, `--force` |
| `make:migration <nombre>` | `migrations/<timestamp>_<nombre>.php` | — |

Detalles de comportamiento:

- **Nunca sobrescribe** un archivo existente: falla con `Ya existe…` salvo `--force`.
- **Nombres derivados**: el modelo calcula la tabla (`User` → `users`,
  `Category` → `categories`, `Address` → `addresses`); el controlador deriva la
  vista del nombre pedido, así `make:controller products` renderiza
  `products.index`.
- **Vistas con jerarquía**: `make:view users.show` crea
  `src/views/users/show.php` (Renderer resuelve el punto como barra).
- **Rutas**: cada archivo de `src/routes/` lo carga el kernel automáticamente;
  `make:route` no modifica `web.php`.
- **Modelos**: `make:model` incluye `created_at`, `updated_at` y `deleted_at`.
  `Model` rellena los sellos de tiempo al guardar (`created_at` solo al crear,
  `updated_at` en cada escritura) y `delete()` hace borrado lógico. Un modelo
  **sólo** se toca si declara esas propiedades con `#[Column]`, así que los
  modelos sobre tablas sin esas columnas siguen funcionando.
  `make:migration create_x_table` genera el DDL con `timestamps()` y
  `softDeletes()` para que las columnas existan.

## Frontend Vue

Los features se habilitan desde la raíz de una app web generada:

```bash
php bin/app feature:enable vue
php bin/app feature:enable material   # elige un único tema visual
# o, en su lugar:
php bin/app feature:enable primevue

npm install
npm run dr2g:dev
```

`vue` agrega una app Vue 3 con TypeScript y Composition API, Vite, y el punto de
montaje en `src/views/layout.php`. En desarrollo el layout usa Vite en
`127.0.0.1:5173`; en producción sirve `public/build/manifest.json`:

```bash
npm run dr2g:typecheck
npm run dr2g:build
```

`material` agrega un tema Material Design CSS propio, sin dependencia npm.
`primevue` instala PrimeVue 4 y configura el preset Aura; los componentes se
importan desde sus módulos en los componentes Vue. Material y PrimeVue son
excluyentes. La elección queda registrada en `.dr2g/features.json`, y los
comandos se pueden repetir sin duplicar la configuración.

## Subviews

Un fragmento reutilizable vive en `src/views/partials/` y se incluye en cualquier
vista, layout o subview con el helper `$subview()`, inyectado por el `Renderer`:

```php
<?= $subview('partials.badge', ['label' => 'Nuevo']) ?>
```

También desde un controlador: `$renderer->subview('partials.badge', [...])`.

- Cada subview se evalúa aislada: sólo ve los datos que se le pasan.
- Pueden anidarse (`partials.outer` que incluye `partials.inner`).
- `make:subview users.row` crea `src/views/partials/users/row.php`.

## Repositories

`make:repository Product` genera un repositorio que extiende
`Dr2g\ErpLibs\Repositories\Repository` y declara el modelo:

```php
final class ProductRepository extends Repository
{
    protected function model(): string
    {
        return Product::class;
    }
}
```

La base aporta `find`, `findOrFail` (lanza 404), `all`, `paginate` (con `items` y
metadatos), `create`, `update`, `delete` y `count`, siempre devolviendo modelos
hidratados. Lo propio del dominio se añade en la clase concreta.

```bash
bin/app make:repository Product --model=Product
```

```bash
bin/app make:controller products          # ProductsController + vista products.index
bin/app make:view products.index
bin/app make:route /products --controller=ProductsController@index
bin/app make:model Product
bin/app make:controller Product --api     # src/Controllers/Api/ProductController.php
```

## Autenticación con RBAC (`make:auth`)

Genera autenticación completa con **usuario → N:N → rol → N:N → permiso**.
La infraestructura vive en la librería (`Dr2g\ErpLibs\Auth`); el proyecto recibe
modelos, repositorios, controladores y vistas.

```bash
bin/app make:auth                 # login + registro
bin/app make:auth --no-register   # sin registro
bin/app migrate
bin/app db:seed                   # permisos + rol admin + usuario admin
```

### Modelo de datos

| Tabla | Contenido |
| --- | --- |
| `users` | name, email (único), password (hash), is_active, timestamps, deleted_at |
| `roles` | name (único), label, description, timestamps, deleted_at |
| `permissions` | name (único, `recurso.accion`), label, timestamps |
| `role_user` | pivote N:N |
| `permission_role` | pivote N:N |

### Conexión en `bootstrap.php`

El comando imprime los pasos. En resumen, tras crear el kernel:

```php
$session = new Dr2g\ErpLibs\Auth\Session\Session();
$session->start();

$auth = new Dr2g\ErpLibs\Auth\AuthManager(
    $session,
    fn ($id) => (new Acme\Repositories\UserRepository())->find((int) $id)
);

$gate = new Dr2g\ErpLibs\Auth\Gate();
```

`src/routes/auth.php` usa `$authController` y `$registerController`; para proteger
un grupo de rutas añade los middleware:

```php
$kernel->router()->use(new Acme\Http\Middleware\StartSession($session));
$kernel->router()->use(new Acme\Http\Middleware\VerifyCsrf($csrf));
```

### Permisos iniciales

`users.{view,create,update,delete}`, `roles.{view,create,update,delete}` y
`permissions.view`. El rol `admin` los recibe todos; el usuario administrador se
crea desde `INSTALL_ADMIN_EMAIL` / `INSTALL_ADMIN_PASSWORD` del `.env`.

### Panel de administración

`make:auth` genera además `/admin`, protegido por sesión y permiso:

| Ruta | Acción | Permiso |
| --- | --- | --- |
| `/admin` | listado de usuarios | `users.view` |
| `/admin/users` | listado, alta, edición con roles y borrado | `users.view` |
| `/admin/roles` | listado, alta, edición con permisos y borrado | `roles.view` |
| `/admin/permissions` | catálogo de permisos | `permissions.view` |

### Seguridad incluida

- `password_hash` / `password_verify` y `needsRehash`.
- Regeneración del id de sesión al entrar y al salir.
- CSRF con `hash_equals` en toda petición no segura.
- Mensaje idéntico si el email no existe o la contraseña falla.
- Redirección de login restringida a rutas internas (`/...`).

## Qué genera `--crud=todo`

| Archivo | Contenido |
| --- | --- |
| `src/Models/Todo.php` | modelo con `title` (requerido, 3–200), `done`, `toggle()`, `isDone()` |
| `migrations/..._create_todos_table.php` | tabla `todos` (SQLite/MySQL) |
| `src/Controllers/TodoController.php` | index, show, create, store, edit, update, toggle, destroy |
| `views/todo/{index,form,show}.php` | listado, formulario y detalle |
| `src/routes/todo.php` | 8 rutas POST/GET |
| `src/tests/Feature/TodoCrudTest.php` | CRUD completo sobre SQLite en memoria |
| `src/Support/Database.php` | resolución de la conexión |

## Estructura del proyecto generado

Regla: **todo lo de la aplicación vive en `src/`**; sólo `public/` (docroot) y
`migrations/` (contrato de `make:migration` / `migrate`) quedan fuera.

```
public/           docroot: index.php + .htaccess
src/Controllers   controladores
src/Http          Kernel / ApiKernel + middleware
src/Models        modelos del ORM
src/Support       utilidades propias
src/routes        web.php, api.php, todo.php
src/views         layout.php, home/, errors/, partials/
src/config        app.php, database.php
src/tests         Unit/, Feature/
migrations/       migraciones
bin/app           CLI del proyecto
bootstrap.php     ensamblado (.env, config, kernel)
composer.json     PSR-4 {Ns}\ => src/
```

## Base de datos del proyecto generado

- **Por defecto SQLite** en `storage/app.sqlite`; el servidor embebido crea la
  tabla al arrancar (`db-init` en `Kernel::run()`), así que funciona sin preparar nada.
- **MySQL**: `DB_CONNECTION=mysql` + `DR2G_DB_*`, y `php bin/app migrate`.

## PSR-4 del proyecto generado

Todo el código PHP vive en `src/` con el namespace `{Ns}\...` (sin el segmento
`App`), y el `composer.json` mapea:

```json
"psr-4": { "Proyect001\": "src/" }
```

Así `src/Http/Kernel.php` corresponde a `Proyect001\Http\Kernel`.

## Verificación sin navegador

```bash
php scripts/smoke_app.php  ../proyect001   # home, 404, 405
php scripts/smoke_crud.php ../proyect001   # CRUD completo sobre SQLite en memoria
```

Los tests que genera el scaffold también pasan tal cual (el kernel web carga
`src/routes`, y los tests de API separan el query string del path):

```bash
cd ../proyect001 && composer install
php vendor/bin/phpunit src/tests
```

## Documentación previa (nuevo proyecto base)


Genera un proyecto arrancable con **ruta + controlador + vistas** conectados a la
librería.

```bash
vendor/bin/erplibs new:app ../mi-erp --namespace="Acme" --name="Mi ERP"
cd ../mi-erp
composer install
php -S localhost:8080 -t public public/index.php
```

## Opciones

| Opción | Default | Descripción |
| --- | --- | --- |
| `<directorio>` | — | destino (debe estar vacío o no existir) |
| `--namespace=` | `App` | raíz PSR-4 (`Acme` o `Acme\Erp`) |
| `--name=` | nombre del directorio | título del layout y nombre del paquete |
| `--force` | `false` | escribe sobre un directorio no vacío |
| `--dry-run` | `false` | lista lo que crearía sin escribir |

## Estructura generada

```
mi-erp/
├── app/
│   ├── Controllers/HomeController.php   controlador de ejemplo
│   └── Http/Kernel.php                  router + renderer + errores
├── routes/web.php                       ruta '/' → HomeController@index
├── views/
│   ├── layout.php                       esqueleto HTML
│   ├── home/index.php                   vista del controlador
│   └── errors/{404,405,500}.php         vistas de error
├── config/app.php, config/database.php
├── public/index.php  public/.htaccess   front controller
├── bin/app                              CLI (delega en vendor/bin/erplibs)
├── tests/Unit/App/KernelTest.php
├── bootstrap.php                        arranca ORM + kernel
└── composer.json
```

## Cómo se conecta el controlador con la vista

1. `public/index.php` carga `bootstrap.php` y llama a `$kernel->loadRoutes('routes')->run()`.
2. El kernel inyecta `kernel` y `renderer` como atributos del `Request`.
3. `HomeController::index()` lee el renderer y devuelve
   `$renderer->layout('home.index', 'layout', $data)`.
4. `Renderer::layout()` renderiza `views/home/index.php` y lo inyecta como `$content`
   en `views/layout.php`.
5. Los errores de routing se dibujan con `views/errors/{status}.php` (404/405/500).

## Añadir una ruta

```php
// routes/web.php
$kernel->router()->get('/clientes', 'Acme\App\Controllers\ClientController@index');
```

## Verificación sin Composer

`scripts/smoke_app.php` levanta la app generada con el autoloader de la librería y
comprueba home, 404 y 405:

```bash
php scripts/smoke_app.php ../mi-erp
```
