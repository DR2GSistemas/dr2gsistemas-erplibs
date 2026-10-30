# Aplicaciones — `erplibs new:app`

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
