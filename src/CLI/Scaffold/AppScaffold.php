<?php

namespace Dr2g\ErpLibs\CLI\Scaffold;

/**
 * Plantillas del proyecto generado por `new:app`.
 *
 * Cada plantilla recibe un contexto (namespace, nombre de proyecto) y devuelve
 * el contenido del archivo. Sin dependencias externas.
 */
final class AppScaffold
{
    /**
     * Prefijo PATH usado cuando existe este repositorio (biblioteca + app en
     * monorepo); vacío para publicar en Packagist.
     */
    public static string $libraryPath = '../../libs/dr2gsistemas_erplibs';

    /**
     * Paquete `slug` válido para Composer: minúsculas y guiones.
     */
    public static function slug(string $value): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $value), '-'));

        return $slug === '' ? 'app' : $slug;
    }

    /**
     * Si la librería existe junto al proyecto, la referencia por `path`;
     * si no, se asume publicación en Packagist/VCS.
     */
    public static function libraryRequirement(): string
    {
        return self::$libraryPath === ''
            ? ",\n        \"dr2gsistemas/erplibs\": \"*\"" : '';
    }

    /**
     * @return array<string, string> ruta relativa => contenido
     */
    public static function files(string $namespace, string $name, string $viewPath): array
    {
        $app = $namespace . '\\App';

        return [
            'composer.json' => self::composer($namespace, $name),
            'bootstrap.php' => self::bootstrap($namespace),
            'bin/app' => self::bin(),
            'config/app.php' => self::configApp($viewPath),
            'config/database.php' => self::configDatabase(),
            'routes/web.php' => self::routes($namespace),
            'app/Controllers/HomeController.php' => self::controller($app),
            'app/Http/Kernel.php' => self::kernel($app),
            'views/layout.php' => self::layout($name),
            'views/home/index.php' => self::homeView($app),
            'views/errors/404.php' => self::errorView(404, 'Página no encontrada'),
            'views/errors/405.php' => self::errorView(405, 'Método no permitido'),
            'views/errors/500.php' => self::errorView(500, 'Error interno'),
            'public/index.php' => self::publicIndex($namespace),
            'public/.htaccess' => self::htaccess(),
            'tests/Unit/App/KernelTest.php' => self::test($app),
            'README.md' => self::readme($namespace, $name),
        ];
    }

    public static function composer(string $namespace, string $name): string
    {
        $package = self::slug(str_replace('\\', '-', $namespace)) . '/' . self::slug($name);
        $require = self::libraryRequirement();
        $libraryPath = self::$libraryPath;

        // JSON necesita barras escapadas: el namespace va con doble barra.
        $phpNamespace = str_replace('\\', '\\\\', $namespace) . '\\\\';

        return <<<JSON
{
    "name": "{$package}",
    "description": "Aplicación ERP generada con dr2gsistemas/erplibs",
    "type": "project",
    "require": {
        "php": ">=8.4"{$require}
    },
    "repositories": [
        {
            "type": "path",
            "url": "{$libraryPath}",
            "options": { "symlink": true }
        }
    ],
    "autoload": {
        "psr-4": {
            "{$phpNamespace}": "app/"
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true,
    "scripts": {
        "serve": "php -S localhost:8080 -t public public/index.php"
    }
}

JSON;
    }

    public static function bootstrap(string $namespace): string
    {
        return <<<PHP
<?php

/*
 * Bootstrap de la aplicación: carga Composer, la configuración y prepara el kernel.
 */

use Dr2g\ErpLibs\Core\DatabaseManager;
use Dr2g\ErpLibs\Models\Model;
use {$namespace}\App\Http\Kernel;
use Dr2g\ErpLibs\View\Renderer;

require_once __DIR__ . '/vendor/autoload.php';

\$config = require __DIR__ . '/config/app.php';
\$database = require __DIR__ . '/config/database.php';

Model::setDbManager(new DatabaseManager(\$database));

\$kernel = new Kernel(new Renderer([\$config['views']]), \$config);

return \$kernel;

PHP;
    }

    public static function bin(): string
    {
        return <<<'PHP'
#!/usr/bin/env php
<?php

/*
 * CLI de la aplicación. Delega en el CLI de la librería después de arrancar
 * el proyecto (composer install).
 */

$root = dirname(__DIR__);

require $root . '/bootstrap.php';

$libraryBin = $root . '/vendor/bin/erplibs';

if (!is_file($libraryBin)) {
    fwrite(STDERR, "No se encontró la librería erplibs. Ejecuta: composer install\n");
    exit(1);
}

$_SERVER['argv'][0] = $libraryBin;

require $libraryBin;

PHP;
    }

    public static function configApp(string $viewPath): string
    {
        return <<<PHP
<?php

/*
 * Configuración de la aplicación generada.
 */

return [
    // Rutas donde el Renderer busca las vistas.
    'views' => __DIR__ . '/../views',
    'debug' => (bool) (getenv('APP_DEBUG') ?: false),
    'name' => '{$viewPath}',
];

PHP;
    }

    public static function configDatabase(): string
    {
        return <<<'PHP'
<?php

/*
 * Configuración de base de datos. Sobrescribir con variables DR2G_DB_*.
 */

$env = static fn (string $key, string $default): string => (string) (getenv($key) ?: $default);

return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => $env('DR2G_DB_DRIVER', 'mysql'),
            'host' => $env('DR2G_DB_HOST', '127.0.0.1'),
            'port' => (int) $env('DR2G_DB_PORT', '3306'),
            'database' => $env('DR2G_DB_NAME', 'app'),
            'username' => $env('DR2G_DB_USER', 'root'),
            'password' => $env('DR2G_DB_PASSWORD', ''),
            'charset' => $env('DR2G_DB_CHARSET', 'utf8mb4'),
        ],
    ],
];

PHP;
    }

    public static function routes(string $namespace): string
    {
        return <<<PHP
<?php

/*
 * Rutas web de la aplicación. Se registran sobre el Kernel.
 *
 * @var {$namespace}\\App\\Http\\Kernel \$kernel
 */

\$kernel->router()->get('/', '{$namespace}\\App\\Controllers\\HomeController@index');

PHP;
    }

    public static function controller(string $app): string
    {
        return <<<PHP
<?php

namespace {$app}\\Controllers;

use Dr2g\ErpLibs\Routing\Controller;
use Dr2g\ErpLibs\Routing\Request;

/**
 * Controlador de ejemplo: renderiza la vista home.index dentro del layout.
 */
class HomeController extends Controller
{
    public function index(Request \$request): mixed
    {
        \$renderer = \$request->attribute('renderer');

        if (!\$renderer instanceof \Dr2g\ErpLibs\View\Renderer) {
            throw new \RuntimeException('Renderer no disponible en la petición.');
        }

        return \$renderer->layout('home.index', 'layout', [
            'title' => 'Hola, ERP',
            'requestedAt' => date('c'),
            'route' => \$request->path,
        ]);
    }
}
PHP;
    }

    public static function kernel(string $app): string
    {
        return <<<PHP
<?php

namespace {$app}\\Http;

use Dr2g\ErpLibs\Routing\Request;
use Dr2g\ErpLibs\Routing\Response;
use Dr2g\ErpLibs\Routing\Router;
use Dr2g\ErpLibs\View\Renderer;

/**
 * Kernel HTTP: router, renderer de vistas y manejo de errores con vistas.
 */
class Kernel
{
    private Router \$router;

    /**
     * @param array<string, mixed> \$config
     */
    public function __construct(
        private readonly Renderer \$renderer,
        private readonly array \$config = []
    ) {
        \$this->router = new Router();
        \$this->registerErrorHandler();
    }

    public static function boot(string \$root): self
    {
        return require \$root . '/bootstrap.php';
    }

    public function router(): Router
    {
        return \$this->router;
    }

    public function renderer(): Renderer
    {
        return \$this->renderer;
    }

    /**
     * Carga las rutas declaradas en routes/*.php.
     */
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
        \$request = (\$request ?? Request::fromGlobals())->withAttributes([
            'kernel' => \$this,
            'renderer' => \$this->renderer,
        ]);

        return \$this->router->dispatch(\$request);
    }

    public function run(): void
    {
        \$this->handle()->send();
    }

    private function registerErrorHandler(): void
    {
        \$this->router->setErrorHandler(function (\Throwable \$error, Request \$request): Response {
            \$status = \$error instanceof \Dr2g\ErpLibs\Routing\Exception\RoutingException
                ? \$error->statusCode
                : 500;

            \$view = \$this->renderer->locate('errors.' . \$status) !== null
                ? 'errors.' . \$status
                : 'errors.500';

            return \$this->renderer->layout(\$view, 'layout', [
                'title' => 'Error ' . \$status,
                'status' => \$status,
                'message' => (\$this->config['debug'] ?? false) ? \$error->getMessage() : 'Ha ocurrido un error.',
            ], \$status);
        });
    }
}

PHP;
    }

    /**
     * Layout del proyecto: recibe `$content` inyectado por Renderer::layout().
     */
    public static function layout(string $name): string
    {
        return <<<PHP
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(\$title ?? '{$name}', ENT_QUOTES) ?></title>
    <style>
        :root { color-scheme: light dark; }
        body { font-family: system-ui, sans-serif; margin: 0; line-height: 1.5; }
        header { padding: 1rem 2rem; border-bottom: 1px solid #8884; }
        main { padding: 2rem; max-width: 60rem; }
        code { background: #8881; padding: .15rem .35rem; border-radius: 4px; }
    </style>
</head>
<body>
<header><strong>{$name}</strong> — generado con dr2g erplibs</header>
<main>
<?= \$content ?>
</main>
</body>
</html>

PHP;
    }

    public static function homeView(string $app): string
    {
        return <<<PHP
<?php
/**
 * Vista de contenido de ejemplo. El layout la envuelve mediante
 * Renderer::layout('home.index', 'layout', \$data) del Kernel.
 *
 * @var string \$title
 * @var string \$requestedAt
 * @var string \$route
 */
?>
<h1><?= htmlspecialchars(\$title, ENT_QUOTES) ?></h1>
<p>Esta página la renderiza <code>{$app}\\Controllers\\HomeController@index</code>.</p>
<dl>
    <dt>Ruta</dt><dd><code><?= htmlspecialchars(\$route, ENT_QUOTES) ?></code></dd>
    <dt>Generada</dt><dd><code><?= htmlspecialchars(\$requestedAt, ENT_QUOTES) ?></code></dd>
</dl>
<p>Añade nuevas rutas en <code>routes/web.php</code>.</p>
PHP;
    }

    public static function errorView(int $status, string $title): string
    {
        return <<<PHP
<?php
/**
 * Vista de error {$status}, envuelta por el layout.
 *
 * @var int \$status
 * @var string \$message
 */
?>
<h1>{$title}</h1>
<p>Código <code><?= (int) \$status ?></code>: <?= htmlspecialchars(\$message, ENT_QUOTES) ?></p>
<p><a href="/">Volver al inicio</a></p>
PHP;
    }

    public static function publicIndex(string $namespace): string
    {
        return <<<PHP
<?php

/*
 * Front controller. Con el servidor embebido de PHP:
 *   php -S localhost:8080 -t public public/index.php
 */

use {$namespace}\\App\\Http\\Kernel;

/** @var Kernel \$kernel */
\$kernel = require dirname(__DIR__) . '/bootstrap.php';

\$kernel->loadRoutes(dirname(__DIR__) . '/routes')->run();

PHP;
    }

    public static function htaccess(): string
    {
        return <<<'TXT'
RewriteEngine On
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
TXT;
    }

    public static function test(string $app): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace AppTests;

use Dr2g\ErpLibs\Routing\Request;
use Dr2g\ErpLibs\View\Renderer;
use {$app}\\Http\\Kernel;
use PHPUnit\Framework\TestCase;

final class KernelTest extends TestCase
{
    private function kernel(): Kernel
    {
        return new Kernel(new Renderer([dirname(__DIR__, 3) . '/views']), ['debug' => false]);
    }

    public function testHomeRendersTheView(): void
    {
        \$kernel = \$this->kernel();
        \$kernel->router()->get('/', '{$app}\\\\Controllers\\\\HomeController@index');

        \$response = \$kernel->handle(Request::create('GET', '/'));

        self::assertSame(200, \$response->status);
        self::assertStringContainsString('Hola, ERP', \$response->body);
        self::assertStringContainsString('generado con dr2g erplibs', \$response->body);
    }

    public function testUnknownRouteRenders404View(): void
    {
        \$response = \$this->kernel()->handle(Request::create('GET', '/nope'));

        self::assertSame(404, \$response->status);
        self::assertStringContainsString('Página no encontrada', \$response->body);
    }
}

PHP;
    }

    public static function readme(string $namespace, string $name): string
    {
        return <<<MD
# {$name}

Aplicación ERP generada con `dr2g erplibs new:app`.

## Arranque

```bash
composer install
php -S localhost:8080 -t public public/index.php
```

Abre <http://localhost:8080>.

## Estructura

```
app/Controllers   controladores (namespace {$namespace}\\\\App\\\\Controllers)
app/Http          Kernel: router + renderer + errores
routes/web.php    rutas web
views/            vistas PHP (layout.php + home/index.php)
public/index.php  front controller
bin/app           CLI (delega en vendor/bin/erplibs)
```

## Vistas

El controlador devuelve `\$renderer->render('home.index', \$data)`. La vista
`views/home/index.php` genera el contenido y lo inserta en `views/layout.php`:

```php
<?php ob_start(); ?>
<h1>...</h1>
<?php \$content = ob_get_clean(); ?>
<?php require dirname(__DIR__) . '/layout.php'; ?>
```

## Rutas

```php
\$kernel->router()->get('/sus', '{$namespace}\\\\App\\\\Controllers\\\\HomeController@index');
```

## Base de datos

Configura `config/database.php` o exporta `DR2G_DB_HOST`, `DR2G_DB_NAME`,
`DR2G_DB_USER`, `DR2G_DB_PASSWORD`.

MD;
    }
}
