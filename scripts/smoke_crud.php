<?php

/*
 * Prueba de humo de una app generada con `app:create --crud=todo`.
 * Usa SQLite en memoria y el autoloader de la librería (sin Composer).
 *
 *   php scripts/smoke_crud.php <ruta-del-proyecto>
 */

use Dr2g\ErpLibs\Core\Connection;
use Dr2g\ErpLibs\Core\DatabaseManager;
use Dr2g\ErpLibs\Models\Model;
use Dr2g\ErpLibs\Routing\Request;
use Dr2g\ErpLibs\View\Renderer;

$project = rtrim(str_replace('\\', '/', $argv[1] ?? dirname(__DIR__) . '/build/proyect001'), '/');

if (!is_dir($project)) {
    fwrite(STDERR, "No existe el directorio: {$project}\n");
    exit(2);
}

$lib = dirname(__DIR__) . '/src';

spl_autoload_register(static function (string $class) use ($lib): void {
    $prefix = 'Dr2g\\ErpLibs\\';

    if (str_starts_with($class, $prefix)) {
        $path = $lib . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

        if (is_file($path)) {
            require $path;
        }
    }
});

$composer = json_decode((string) file_get_contents($project . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$psr4 = $composer['autoload']['psr-4'] ?? [];

spl_autoload_register(static function (string $class) use ($project, $psr4): void {
    foreach ($psr4 as $prefix => $relative) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $suffix = str_replace('\\', '/', substr($class, strlen($prefix)));
        $base = $project . '/' . trim($relative, '/');
        $path = $base . '/' . $suffix . '.php';

        if (is_file($path)) {
            require $path;
        }
    }
});

// El scaffold mapea {Ns}\\ => src/, así que la raíz del namespace es {Ns}.
$namespace = rtrim((string) array_key_first($psr4), '\\');
$kernelClass = $namespace . '\\Http\\Kernel';
$controllerClass = $namespace . '\\Controllers\\TodoController';

$connection = new Connection(['dsn' => 'sqlite::memory:']);
$connection->execute(
    'CREATE TABLE todos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title VARCHAR(200) NOT NULL,
        done INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NULL,
        updated_at TEXT NULL
    )'
);

$manager = new DatabaseManager(['default' => 'test', 'connections' => ['test' => []]]);
(new ReflectionProperty(DatabaseManager::class, 'connections'))->setValue($manager, ['test' => $connection]);
Model::setDbManager($manager);
Model::flushMetadata();

$kernel = new $kernelClass(new Renderer([$project . '/views']), ['debug' => false]);
$router = $kernel->router();

$router->get('/', static fn () => \Dr2g\ErpLibs\Routing\Response::redirect('/todos'));
$router->get('/todos', $controllerClass . '@index');
$router->get('/todos/new', $controllerClass . '@create');
$router->post('/todos', $controllerClass . '@store');
$router->get('/todos/{id}', $controllerClass . '@show');
$router->get('/todos/{id}/edit', $controllerClass . '@edit');
$router->post('/todos/{id}', $controllerClass . '@update');
$router->post('/todos/{id}/toggle', $controllerClass . '@toggle');
$router->post('/todos/{id}/delete', $controllerClass . '@destroy');

$failures = 0;
$check = static function (string $name, bool $ok, string $detail = '') use (&$failures): void {
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . ($ok ? '' : ' | ' . $detail) . "\n";
    $failures += $ok ? 0 : 1;
};

$call = static fn (string $method, string $path, string $body = '') => $kernel->handle(
    Request::create($method, $path, body: $body, headers: $body !== '' ? ['Content-Type' => 'application/json'] : [])
);

$home = $call('GET', '/');
$check('GET / redirige al listado de tareas', $home->status === 302 && ($home->headers['Location'] ?? '') === '/todos', "status {$home->status}");

$empty = $call('GET', '/todos');
$check('GET /todos sin tareas', $empty->status === 200 && str_contains($empty->body, 'No hay tareas'), "status {$empty->status}");

$form = $call('GET', '/todos/new');
$check('GET /todos/new muestra formulario', $form->status === 200 && str_contains($form->body, 'name="title"'), "status {$form->status}");

$created = $call('POST', '/todos', '{"title":"Comprar pan"}');
$check('POST /todos crea y redirige', $created->status === 303 && ($created->headers['Location'] ?? '') === '/todos', "status {$created->status}");

$second = $call('POST', '/todos', '{"title":"Escribir informe"}');
$check('POST /todos segunda tarea', $second->status === 303, "status {$second->status}");

$list = $call('GET', '/todos');
$check('GET /todos lista ambas', str_contains($list->body, 'Comprar pan') && str_contains($list->body, 'Escribir informe'));

$show = $call('GET', '/todos/1');
$check('GET /todos/1 muestra pendiente', $show->status === 200 && str_contains($show->body, 'Pendiente'), "status {$show->status}");

$edit = $call('GET', '/todos/1/edit');
$check('GET /todos/1/edit precarga el título', str_contains($edit->body, 'value="Comprar pan"'), "status {$edit->status}");

$toggle = $call('POST', '/todos/1/toggle');
$check('POST /todos/1/toggle redirige', $toggle->status === 303, "status {$toggle->status}");
$check('la tarea quedó completada', str_contains($call('GET', '/todos/1')->body, 'Completada'));

$updated = $call('POST', '/todos/1', '{"title":"Comprar pan integral","done":true}');
$check('POST /todos/1 actualiza', $updated->status === 303 && str_contains($call('GET', '/todos/1')->body, 'Comprar pan integral'), "status {$updated->status}");

$invalid = $call('POST', '/todos', '{"title":"ab"}');
$check('validación devuelve 422 con el formulario', $invalid->status === 422 && str_contains($invalid->body, 'at least 3 characters'), "status {$invalid->status}");

$missing = $call('GET', '/todos/99');
$check('tarea inexistente devuelve 404', $missing->status === 404 && str_contains($missing->body, 'Página no encontrada'), "status {$missing->status}");

$deleted = $call('POST', '/todos/1/delete');
$check('POST /todos/1/delete redirige', $deleted->status === 303, "status {$deleted->status}");
$check('la tarea desaparece del listado', !str_contains($call('GET', '/todos')->body, 'Comprar pan integral'));

$rows = $connection->select('SELECT COUNT(*) AS total FROM todos');
$check('queda 1 tarea en la base', (int) $rows[0]['total'] === 1, 'total=' . $rows[0]['total']);

echo "\n" . ($failures === 0 ? 'OK: CRUD completo' : "{$failures} fallo(s)") . PHP_EOL;

exit($failures === 0 ? 0 : 1);
