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
     * Fija la ruta de la librería (relativa al proyecto generado) si existe
     * realmente; si no, se usa la publicada en Packagist.
     */
    public static function useLibraryPath(string $projectBase, ?string $libraryDir = null): void
    {
        $projectBase = str_replace('\\', '/', $projectBase);
        $candidates = [];

        if ($libraryDir !== null) {
            $candidates[] = $libraryDir;
        }

        $candidates[] = dirname($projectBase, 2) . '/libs/dr2gsistemas_erplibs';
        $candidates[] = $projectBase . '/../libs/dr2gsistemas_erplibs';

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                $relative = self::relativePath($projectBase, (string) realpath($candidate));
                self::$libraryPath = $relative;

                return;
            }
        }

        self::$libraryPath = '';
    }

    /**
     * Ruta relativa POSIX entre dos directorios absolutos.
     */
    public static function relativePath(string $from, string $to): string
    {
        $from = array_values(array_filter(explode('/', str_replace('\\', '/', $from))));
        $to = array_values(array_filter(explode('/', str_replace('\\', '/', $to))));

        while ($from !== [] && $to !== [] && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        return str_repeat('../', count($from)) . implode('/', $to);
    }

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
        // Siempre se declara el paquete: con `path` resuelve al monorepo; en
        // Packagist/VCS basta con borrar la entrada `repositories`.
        return ",\n        \"dr2gsistemas/erplibs\": \"*\"";
    }

    /**
     * @return array<string, string> ruta relativa => contenido
     */
    public static function files(string $namespace, string $name, string $viewPath, bool $withCrud = false): array
    {
        // {Ns}\App\Http\Kernel -> app/Http/Kernel.php (PSR-4 {Ns}\ => app/)
        $app = $namespace . '\\App';

        return [
            'composer.json' => self::composer($namespace, $name),
            'bootstrap.php' => self::bootstrap($namespace),
            'bin/app' => self::bin(),
            'config/app.php' => self::configApp($viewPath),
            'config/database.php' => self::configDatabase(),
            '.env.template' => self::envTemplate($namespace, $name, $withCrud),
            '.env' => self::envFile($namespace, $name, $withCrud),
            '.env.local.template' => self::envLocalTemplate($namespace, $name),
            'routes/web.php' => self::routes($namespace, $withCrud),
            'app/Controllers/HomeController.php' => self::controller($app),
            'app/Http/Kernel.php' => self::kernel($app),
            'views/layout.php' => self::layout($name),
            'views/home/index.php' => self::homeView($app),
            'views/partials/nav.php' => self::nav(),
            'views/errors/404.php' => self::errorView(404, 'Página no encontrada'),
            'views/errors/405.php' => self::errorView(405, 'Método no permitido'),
            'views/errors/500.php' => self::errorView(500, 'Error interno'),
            'public/index.php' => self::publicIndex($namespace),
            'public/.htaccess' => self::htaccess(),
            'tests/Unit/App/KernelTest.php' => self::test($app),
            '.gitignore' => self::gitignore(),
            'README.md' => self::readme($namespace, $name),
        ];
    }

    public static function composer(string $namespace, string $name): string
    {
        $package = self::slug(str_replace('\\', '-', $namespace)) . '/' . self::slug($name);
        $libraryPath = self::$libraryPath;

        // JSON necesita barras escapadas: Proyect001\App\ => "Proyect001\\App\\".
        $phpNamespace = str_replace('\\', '\\\\', $namespace) . '\\\\App\\\\';

        return <<<JSON
{
    "name": "{$package}",
    "description": "Aplicación ERP generada con dr2gsistemas/erplibs",
    "type": "project",
    "require": {
        "php": ">=8.4",
        "dr2gsistemas/erplibs": "*"
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
 * Bootstrap de la aplicación: carga el entorno (.env), Composer y prepara el kernel.
 */

use Dr2g\ErpLibs\Config\DotEnv;
use Dr2g\ErpLibs\Core\DatabaseManager;
use Dr2g\ErpLibs\Models\Model;
use Dr2g\ErpLibs\View\Renderer;
use {$namespace}\App\Http\Kernel;

require_once __DIR__ . '/vendor/autoload.php';

// Entorno: .env primero, .env.local después (gana este último).
DotEnv::load(__DIR__ . '/.env');
DotEnv::load(__DIR__ . '/.env.local');

\$config = require __DIR__ . '/config/app.php';
\$database = require __DIR__ . '/config/database.php';

date_default_timezone_set((string) (\$config['timezone'] ?? 'UTC'));

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
 * CLI de la aplicación. Delega en el CLI de la librería (vendor/bin/erplibs)
 * usando este proyecto como raíz de trabajo.
 */

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

// Entorno del proyecto (.env + .env.local)
Dr2g\ErpLibs\Config\DotEnv::load($root . '/.env');
Dr2g\ErpLibs\Config\DotEnv::load($root . '/.env.local');

$libraryBin = $root . '/vendor/bin/erplibs';

if (!is_file($libraryBin)) {
    fwrite(STDERR, "No se encontró la librería erplibs. Ejecuta: composer install\n");
    exit(1);
}

$_SERVER['argv'][0] = $libraryBin;

// `bin/app app:serve` sirve este proyecto por defecto y usa APP_PORT del .env.
if (($_SERVER['argv'][1] ?? '') === 'app:serve') {
    $hasPort = array_filter(array_slice($_SERVER['argv'], 2), static fn (string $arg): bool => str_starts_with($arg, '--port')) !== [];

    if (!$hasPort && ($port = getenv('APP_PORT')) !== false && $port !== '') {
        $_SERVER['argv'][] = '--port=' . $port;
    }

    $_SERVER['argv'][] = $root;
}

require $libraryBin;

PHP;
    }

    /**
     * `.env.template`: variables de la aplicación, motor de BD y datos iniciales.
     */
    public static function envTemplate(string $namespace, string $name, bool $withCrud = false): string
    {
        $slug = self::slug($namespace . '-' . $name);
        $sqlitePath = 'storage/' . self::slug($name) . '.sqlite';
        $todo = $withCrud
            ? "# --- Datos iniciales del CRUD de prueba (--crud=todo) ---\nSEED_TODOS=true\nSEED_TODO_TITLE=\"Primera tarea de prueba\"\n\n"
            : '';

        return <<<ENV
# =============================================================================
# {$name} — plantilla de variables de entorno
#
#   cp .env.template .env.local    (app:create ya genera un .env)
#
# Nunca subas .env/.env.local al repositorio: contienen credenciales.
# =============================================================================

# --- Aplicación ---------------------------------------------------------------
APP_NAME="{$name}"
APP_ENV=local                 # local | staging | production
APP_DEBUG=true                # false en producción
APP_URL=http://localhost:8000
APP_PORT=8000                 # puerto por defecto de `bin/app app:serve`
APP_TIMEZONE=UTC
APP_LOCALE=es
APP_KEY=                      # php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"

# --- Motor de base de datos ---------------------------------------------------
# sqlite | mysql | mariadb | pgsql
DB_CONNECTION=sqlite
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE={$sqlitePath}       # con sqlite: ruta relativa al proyecto
DB_USERNAME=root
DB_PASSWORD=
DB_CHARSET=utf8mb4
# DB_DSN=                     # opcional: DSN completo, tiene prioridad

# --- Otros datos iniciales ----------------------------------------------------
INSTALL_ADMIN_NAME="Administrador"
INSTALL_ADMIN_EMAIL=admin@localhost
INSTALL_ADMIN_PASSWORD=       # vacío: se genera al instalar
MAIL_FROM=no-reply@localhost
MAIL_TRANSPORT=log            # log | smtp
LOG_LEVEL=warning

{$todo}# --- Identidad generada por app:create ----------------------------------------
PROJECT_NAMESPACE={$namespace}
PROJECT_SLUG={$slug}
ENV;
    }

    /**
     * `.env` listo para usar: parte de la plantilla y genera `APP_KEY` y la
     * contraseña inicial del administrador.
     */
    public static function envFile(string $namespace, string $name, bool $withCrud = false): string
    {
        $secrets = [
            'APP_KEY' => bin2hex(random_bytes(32)),
            'INSTALL_ADMIN_PASSWORD' => bin2hex(random_bytes(8)),
        ];

        $lines = [];

        foreach (explode("\n", self::envTemplate($namespace, $name, $withCrud)) as $line) {
            $trimmed = ltrim($line);

            // Comentarios y líneas vacías se copian tal cual.
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                $lines[] = $line;
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            if (isset($secrets[$key])) {
                $lines[] = $key . '=' . $secrets[$key];
                continue;
            }

            // El comentario en línea pasa a la línea anterior para que getenv() sea limpio.
            if (preg_match('/^(.*?)(\s{2,}#.*)$/', $value, $match) === 1) {
                $value = $match[1];
                $lines[] = trim($match[2]);
            }

            $lines[] = $key . '=' . trim($value);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Variables locales (credenciales reales) que no se versionan.
     */
    public static function envLocalTemplate(string $namespace, string $name): string
    {
        $database = 'app_' . self::slug($name);

        return <<<ENV
# =============================================================================
# {$name} — overrides locales (no se versiona)
#
#   cp .env.local.template .env.local
#
# Se carga DESPUÉS de .env; los valores de este archivo ganan.
# =============================================================================

APP_ENV=local
APP_DEBUG=true

# Credenciales reales del entorno
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE={$database}
DB_USERNAME=root
DB_PASSWORD=
ENV;
    }

    public static function configApp(string $viewPath): string
    {
        return <<<PHP
<?php

/*
 * Configuración de la aplicación. Los valores vienen de .env (ver .env.template).
 */

return [
    'views' => __DIR__ . '/../views',
    'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'name' => getenv('APP_NAME') ?: '{$viewPath}',
    'env' => getenv('APP_ENV') ?: 'local',
    'url' => getenv('APP_URL') ?: 'http://localhost:8000',
    'port' => (int) (getenv('APP_PORT') ?: 8000),
    'timezone' => getenv('APP_TIMEZONE') ?: 'UTC',
    'locale' => getenv('APP_LOCALE') ?: 'es',
    'key' => getenv('APP_KEY') ?: '',
    'mail' => [
        'from' => getenv('MAIL_FROM') ?: 'no-reply@localhost',
        'transport' => getenv('MAIL_TRANSPORT') ?: 'log',
    ],
    'install' => [
        'admin_name' => getenv('INSTALL_ADMIN_NAME') ?: 'Administrador',
        'admin_email' => getenv('INSTALL_ADMIN_EMAIL') ?: 'admin@localhost',
        'admin_password' => getenv('INSTALL_ADMIN_PASSWORD') ?: '',
    ],
    // Base de datos del servidor embebido (sqlite:... o null para no preparar nada).
    'database' => getenv('APP_DATABASE') ?: (str_starts_with((string) (getenv('DB_CONNECTION') ?: 'sqlite'), 'sqlite')
        ? 'sqlite:' . __DIR__ . '/../storage/app.sqlite'
        : null),
];

PHP;
    }

    public static function configDatabase(): string
    {
        return <<<'PHP'
<?php

/*
 * Motor de base de datos. Lee .env (ver .env.template):
 *
 *   DB_CONNECTION=sqlite|mysql|mariadb|pgsql
 *   DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, DB_CHARSET
 *   DB_DSN (opcional: DSN completo, tiene prioridad sobre los campos sueltos)
 */

$env = static fn (string $key, string $default = ''): string => (string) (getenv($key) === false ? $default : getenv($key));

$storage = __DIR__ . '/../storage';

if (!is_dir($storage)) {
    @mkdir($storage, 0755, true);
}

$connection = strtolower($env('DB_CONNECTION', 'sqlite')) ?: 'sqlite';
$dsn = $env('DB_DSN');

if ($connection === 'sqlite') {
    $database = $env('DB_DATABASE', 'storage/app.sqlite');
    $absolute = str_starts_with($database, '/') || preg_match('#^[A-Za-z]:[\\/]#', $database) === 1;
    $file = $absolute ? $database : dirname(__DIR__) . '/' . ltrim($database, '/');

    return [
        'default' => 'sqlite',
        'connections' => [
            'sqlite' => [
                'dsn' => $dsn !== '' ? $dsn : 'sqlite:' . $file,
            ],
        ],
    ];
}

$driver = $connection === 'mariadb' ? 'mysql' : $connection;
$host = $env('DB_HOST', '127.0.0.1');
$port = (int) $env('DB_PORT', $driver === 'pgsql' ? '5432' : '3306');
$database = $env('DB_DATABASE', 'app');
$charset = $env('DB_CHARSET', $driver === 'pgsql' ? 'utf8' : 'utf8mb4');

return [
    'default' => $connection,
    'connections' => [
        $connection => [
            'driver' => $driver,
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $env('DB_USERNAME', 'root'),
            'password' => $env('DB_PASSWORD'),
            'charset' => $charset,
            'dsn' => $dsn !== '' ? $dsn : sprintf(
                '%s:host=%s;port=%d;dbname=%s;charset=%s',
                $driver,
                $host,
                $port,
                $database,
                $charset
            ),
        ],
    ],
];

PHP;
    }

    public static function routes(string $namespace, bool $withCrud = false): string
    {
        $crudJs = $withCrud ? 'true' : 'false';
        $crud = $withCrud ? "\nrequire __DIR__ . '/todo.php';" : '';

        return <<<PHP
<?php

/*
 * Rutas web de la aplicación. Se registran sobre el Kernel.
 *
 * @var {$namespace}\\App\\Http\\Kernel \$kernel
 */

if ({$crudJs}) {
    \$kernel->router()->get('/', static function () {
        // Datos iniciales declarados en .env (SEED_TODOS / SEED_TODO_TITLE).
        if (filter_var(getenv('SEED_TODOS') ?: 'false', FILTER_VALIDATE_BOOLEAN)
            && \\{$namespace}\\App\\Models\\Todo::query()->count() === 0) {
            \\{$namespace}\\App\\Models\\Todo::create([
                'title' => getenv('SEED_TODO_TITLE') ?: 'Primera tarea de prueba',
            ]);
        }

        return \Dr2g\ErpLibs\Routing\Response::redirect('/todos');
    });
} else {
    \$kernel->router()->get('/', '{$namespace}\\App\\Controllers\\HomeController@index');
}
{$crud}

PHP;
    }

    public static function nav(): string
    {
        return <<<'PHP'
<nav>
    <a href="/">Inicio</a> ·
    <a href="/todos">Tareas</a> ·
    <a href="/todos/new">Nueva tarea</a>
</nav>

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
use {$app}\Support\Database;

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
        \$this->prepareDevelopmentDatabase();

        \$this->handle()->send();
    }

    /**
     * Servidor embebido: crea las tablas del CRUD de ejemplo cuando la base de
     * datos es SQLite, para probar sin instalar MySQL.
     */
    private function prepareDevelopmentDatabase(): void
    {
        \$driver = \$this->config['database'] ?? null;

        if (!is_string(\$driver) || !str_starts_with(\$driver, 'sqlite:')) {
            return;
        }

        try {
            \$connection = Database::connect(require dirname(__DIR__, 2) . '/config/database.php');

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
            // Sin base de datos el servidor sigue sirviendo el inicio y los errores.
            error_log('DB init skipped: ' . \$error->getMessage());
        }
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
<?php if (is_file(__DIR__ . '/partials/nav.php')) { require __DIR__ . '/partials/nav.php'; } ?>
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

    public static function gitignore(): string
    {
        return <<<'TXT'
# Entorno: sólo se versionan las plantillas
.env
.env.local
.env.*.local

/vendor/
/storage/*.sqlite
/storage/*.log
composer.lock
.phpunit.cache/
.phpunit.result.cache
TXT;
    }

    public static function readme(string $namespace, string $name): string
    {
        return <<<MD
# {$name}

Aplicación ERP generada con `dr2g erplibs new:app`.

## Arranque

```bash
composer install
php bin/app app:serve --port 8000     # equivalente a php -S con este proyecto
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
