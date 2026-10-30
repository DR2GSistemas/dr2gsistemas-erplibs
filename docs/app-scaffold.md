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

## Qué genera `--crud=todo`

| Archivo | Contenido |
| --- | --- |
| `app/Models/Todo.php` | modelo con `title` (requerido, 3–200), `done`, `toggle()`, `isDone()` |
| `migrations/..._create_todos_table.php` | tabla `todos` (SQLite/MySQL) |
| `app/Controllers/TodoController.php` | index, show, create, store, edit, update, toggle, destroy |
| `views/todo/{index,form,show}.php` | listado, formulario y detalle |
| `routes/todo.php` | 8 rutas POST/GET |
| `tests/Feature/TodoCrudTest.php` | CRUD completo sobre SQLite en memoria |
| `app/Support/Database.php` | resolución de la conexión |

## Base de datos del proyecto generado

- **Por defecto SQLite** en `storage/app.sqlite`; el servidor embebido crea la
  tabla al arrancar (`db-init` en `Kernel::run()`), así que funciona sin preparar nada.
- **MySQL**: `DB_CONNECTION=mysql` + `DR2G_DB_*`, y `php bin/app migrate`.

## PSR-4 del proyecto generado

Los archivos viven en `app/` con el namespace `{Ns}\App\...`, y el
`composer.json` mapea:

```json
"psr-4": { "Proyect001\App\": "app/" }
```

Así `app/Http/Kernel.php` corresponde a `Proyect001\App\Http\Kernel`.

## Verificación sin navegador

```bash
php scripts/smoke_app.php  ../proyect001   # home, 404, 405
php scripts/smoke_crud.php ../proyect001   # CRUD completo sobre SQLite en memoria
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
