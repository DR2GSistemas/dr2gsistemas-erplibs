<?php

namespace Dr2g\ErpLibs\CLI\Scaffold;

/**
 * Plantillas de la aplicación CRUD de ejemplo (todolist de prueba) que
 * `app:create --crud=todo` añade al proyecto generado.
 *
 * Modelo, migración, controller REST, vistas, helper de base de datos y
 * tests de punta a punta con SQLite en memoria.
 */
final class CrudScaffold
{
    /**
     * @return array<string, string> ruta relativa => contenido
     */
    public static function files(string $namespace, bool $withWeb = true): array
    {
        $app = $namespace;

        $files = [
            'app/Models/Todo.php' => self::model($app),
            'migrations/2026_01_01_000000_create_todos_table.php' => self::migration($app),
            'app/Support/Database.php' => self::database($app),
        ];

        if (!$withWeb) {
            // Modo API: sólo modelo, migración y soporte de BD.
            return $files;
        }

        return $files + [
            'routes/todo.php' => CrudScaffold::routes($namespace),
            'app/Controllers/TodoController.php' => self::controller($app),
            'views/todo/index.php' => self::indexView($app),
            'views/todo/form.php' => self::formView($app),
            'views/todo/show.php' => self::showView($app),
            'tests/Feature/TodoCrudTest.php' => self::test($app),
        ];
    }

    public static function routes(string $namespace): string
    {
        return <<<PHP
<?php

/*
 * Rutas del CRUD de ejemplo (todolist). Las carga routes/web.php.
 *
 * @var {$namespace}\\Http\\Kernel \$kernel
 */

\$todo = '{$namespace}\\Controllers\\TodoController';

\$kernel->router()->get('/todos', "{\$todo}@index");
\$kernel->router()->get('/todos/new', "{\$todo}@create");
\$kernel->router()->post('/todos', "{\$todo}@store");
\$kernel->router()->get('/todos/{id}', "{\$todo}@show");
\$kernel->router()->get('/todos/{id}/edit', "{\$todo}@edit");
\$kernel->router()->post('/todos/{id}', "{\$todo}@update");
\$kernel->router()->post('/todos/{id}/toggle', "{\$todo}@toggle");
\$kernel->router()->post('/todos/{id}/delete', "{\$todo}@destroy");

PHP;
    }

    public static function model(string $app): string
    {
        return <<<PHP
<?php

namespace {$app}\\Models;

use Dr2g\ErpLibs\Models\Attributes\Column;
use Dr2g\ErpLibs\Models\Attributes\Table;
use Dr2g\ErpLibs\Models\Model;

/**
 * Tarea del todolist de prueba.
 */
#[Table(name: 'todos')]
class Todo extends Model
{
    #[Column(type: 'VARCHAR(200)', validation: ['required', 'min:3', 'max:200'])]
    public string \$title;

    #[Column(type: 'BOOLEAN', default: false)]
    public bool \$done = false;

    public function toggle(): self
    {
        \$this->fill(['done' => !\$this->done])->save();

        return \$this;
    }

    public function isDone(): bool
    {
        return (bool) (\$this->done);
    }
}

PHP;
    }

    public static function migration(string $app): string
    {
        return <<<PHP
<?php

use Dr2g\ErpLibs\Migrations\Migration;

/**
 * Tabla del todolist de prueba.
 */
class CreateTodosTable extends Migration
{
    public function up(): void
    {
        // SQLite no admite AUTO_INCREMENT en el DDL del ORM: usa su equivalente.
        if (\$this->connection()->getDriver() === 'sqlite') {
            \$this->connection()->execute(
                'CREATE TABLE IF NOT EXISTS todos (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title VARCHAR(200) NOT NULL,
                    done INTEGER NOT NULL DEFAULT 0,
                    created_at TEXT NULL,
                    updated_at TEXT NULL
                )'
            );

            return;
        }

        \$this->schema->create('todos', function (\$table) {
            \$table->id();
            \$table->string('title', 200);
            \$table->boolean('done')->default(false);
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        \$this->schema->drop('todos');
    }
}

PHP;
    }

    public static function controller(string $app): string
    {
        return <<<PHP
<?php

namespace {$app}\\Controllers;

use {$app}\\Models\\Todo;
use Dr2g\ErpLibs\Cache\CachesResponses;
use Dr2g\ErpLibs\Exceptions\ValidationException;
use Dr2g\ErpLibs\Routing\Exception\NotFoundException;
use Dr2g\ErpLibs\Routing\Request;
use Dr2g\ErpLibs\Routing\Response;

/**
 * CRUD del todolist de prueba. Cada acción devuelve una vista o redirige.
 */
class TodoController
{
    use CachesResponses;

    public function index(Request \$request): mixed
    {
        // Listado cacheado (CACHE_DRIVER=redis en .env). ?refresh=1 fuerza recarga.
        if (\$this->bypassCache(\$request)) {
            \$this->forgetCache(['todos']);
        }

        return \$this->render(\$request, 'todo.index', [
            'title' => 'Todolist',
            'todos' => \$this->cached('all', static fn (): array => Todo::all(), tags: ['todos'], ttl: 60),
        ]);
    }

    public function show(Request \$request, int \$id): mixed
    {
        return \$this->render(\$request, 'todo.show', [
            'title' => 'Tarea',
            'todo' => \$this->find(\$id),
        ]);
    }

    public function create(Request \$request): mixed
    {
        return \$this->render(\$request, 'todo.form', [
            'title' => 'Nueva tarea',
            'todo' => null,
            'error' => null,
        ]);
    }

    public function store(Request \$request): Response
    {
        try {
            Todo::create(\$this->payload(\$request));
        } catch (ValidationException \$error) {
            return \$this->render(\$request, 'todo.form', [
                'title' => 'Nueva tarea',
                'todo' => null,
                'error' => \$error->getMessage(),
            ])->withStatus(422);
        }

        \$this->forgetCache(['todos']);

        return Response::redirect('/todos', 303);
    }

    public function edit(Request \$request, int \$id): mixed
    {
        return \$this->render(\$request, 'todo.form', [
            'title' => 'Editar tarea',
            'todo' => \$this->find(\$id),
            'error' => null,
        ]);
    }

    public function update(Request \$request, int \$id): Response
    {
        try {
            \$this->find(\$id)->fill(\$this->payload(\$request))->save();
        } catch (ValidationException \$error) {
            return \$this->render(\$request, 'todo.form', [
                'title' => 'Editar tarea',
                'todo' => null,
                'error' => \$error->getMessage(),
            ])->withStatus(422);
        }

        \$this->forgetCache(['todos']);

        return Response::redirect('/todos', 303);
    }

    public function toggle(Request \$request, int \$id): Response
    {
        \$this->find(\$id)->toggle();

        \$this->forgetCache(['todos']);

        return Response::redirect('/todos', 303);
    }

    public function destroy(Request \$request, int \$id): Response
    {
        \$this->find(\$id)->delete();

        \$this->forgetCache(['todos']);

        return Response::redirect('/todos', 303);
    }

    /**
     * Normaliza la entrada del formulario: checkbox "on" → true, ausente → false.
     *
     * @return array<string, mixed>
     */
    private function payload(Request \$request): array
    {
        \$data = \$request->input();
        \$data['done'] = in_array(\$data['done'] ?? null, ['1', 'on', 'true', true, 1], true);

        return \$data;
    }

    private function find(int \$id): Todo
    {
        \$todo = Todo::find(\$id);

        return \$todo ?? throw new NotFoundException('Tarea no encontrada.');
    }

    /**
     * @param array<string, mixed> \$data
     */
    private function render(Request \$request, string \$view, array \$data): Response
    {
        \$renderer = \$request->attribute('renderer');

        if (!\$renderer instanceof \Dr2g\ErpLibs\View\Renderer) {
            throw new \RuntimeException('Renderer no disponible en la petición.');
        }

        return \$renderer->layout(\$view, 'layout', \$data);
    }
}

PHP;
    }

    public static function database(string $app): string
    {
        return <<<PHP
<?php

namespace {$app}\\Support;

use Dr2g\ErpLibs\Core\Connection;

/**
 * Resuelve la conexión configurada en config/database.php.
 */
final class Database
{
    /**
     * @param array<string, mixed> \$config
     */
    public static function connect(array \$config): Connection
    {
        \$name = \$config['default'] ?? 'mysql';
        \$settings = \$config['connections'][\$name] ?? [];

        if (\$settings === []) {
            throw new \\RuntimeException("Conexión de base de datos '{\$name}' no configurada.");
        }

        return new Connection(\$settings);
    }
}

PHP;
    }

    public static function indexView(string $app): string
    {
        return <<<PHP
<?php
/**
 * Listado del todolist.
 *
 * @var string \$title
 * @var list<\\{$app}\\Models\\Todo> \$todos
 */
?>
<h1><?= htmlspecialchars(\$title, ENT_QUOTES) ?></h1>

<p><a href="/todos/new">+ Nueva tarea</a></p>

<?php if (\$todos === []): ?>
    <p>No hay tareas todavía.</p>
<?php else: ?>
    <table>
        <thead><tr><th></th><th>Tarea</th><th>Creada</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach (\$todos as \$todo): ?>
            <tr>
                <td><?= \$todo->isDone() ? '✓' : '·' ?></td>
                <td><a href="/todos/<?= (int) \$todo->toArray()['id'] ?>"><?= htmlspecialchars((string) \$todo->toArray()['title'], ENT_QUOTES) ?></a></td>
                <td><code><?= htmlspecialchars((string) (\$todo->toArray()['created_at'] ?? ''), ENT_QUOTES) ?></code></td>
                <td>
                    <form method="post" action="/todos/<?= (int) \$todo->toArray()['id'] ?>/toggle" style="display:inline">
                        <button type="submit"><?= \$todo->isDone() ? 'Reabrir' : 'Completar' ?></button>
                    </form>
                    <a href="/todos/<?= (int) \$todo->toArray()['id'] ?>/edit">Editar</a>
                    <form method="post" action="/todos/<?= (int) \$todo->toArray()['id'] ?>/delete" style="display:inline">
                        <button type="submit">Borrar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
PHP;
    }

    public static function formView(string $app): string
    {
        return <<<PHP
<?php
/**
 * Formulario de creación y edición.
 *
 * @var string \$title
 * @var null|\\{$app}\\Models\\Todo \$todo
 * @var null|string \$error
 */
\$editing = \$todo !== null;
\$data = \$editing ? \$todo->toArray() : [];
\$action = \$editing ? '/todos/' . (int) (\$data['id'] ?? 0) : '/todos';
?>
<h1><?= htmlspecialchars(\$title, ENT_QUOTES) ?></h1>

<?php if (\$error !== null): ?>
    <p role="alert"><strong>Error:</strong> <?= htmlspecialchars(\$error, ENT_QUOTES) ?></p>
<?php endif; ?>

<form method="post" action="<?= htmlspecialchars(\$action, ENT_QUOTES) ?>">
    <p>
        <label for="title">Tarea</label><br>
        <input id="title" name="title" value="<?= htmlspecialchars((string) (\$data['title'] ?? ''), ENT_QUOTES) ?>" required minlength="3" maxlength="200">
    </p>
    <p>
        <label>
            <input type="checkbox" name="done" value="1" <?= !empty(\$data['done']) ? 'checked' : '' ?>>
            Completada
        </label>
    </p>
    <p>
        <button type="submit"><?= \$editing ? 'Guardar' : 'Crear' ?></button>
        <a href="/todos">Cancelar</a>
    </p>
</form>
PHP;
    }

    public static function showView(string $app): string
    {
        return <<<PHP
<?php
/**
 * Detalle de una tarea.
 *
 * @var string \$title
 * @var \\{$app}\\Models\\Todo \$todo
 */
\$data = \$todo->toArray();
\$id = (int) (\$data['id'] ?? 0);
?>
<h1><?= htmlspecialchars((string) (\$data['title'] ?? ''), ENT_QUOTES) ?></h1>

<dl>
    <dt>Estado</dt><dd><?= \$todo->isDone() ? 'Completada' : 'Pendiente' ?></dd>
    <dt>Creada</dt><dd><code><?= htmlspecialchars((string) (\$data['created_at'] ?? ''), ENT_QUOTES) ?></code></dd>
</dl>

<p>
    <a href="/todos/<?= \$id ?>/edit">Editar</a> ·
    <a href="/todos">Volver al listado</a>
</p>
PHP;
    }

    public static function test(string $app): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace AppTests;

use Dr2g\ErpLibs\Core\Connection;
use Dr2g\ErpLibs\Core\DatabaseManager;
use Dr2g\ErpLibs\Models\Model;
use Dr2g\ErpLibs\Routing\Request;
use Dr2g\ErpLibs\View\Renderer;
use {$app}\\Http\\Kernel;
use PHPUnit\Framework\TestCase;

/**
 * Prueba de punta a punta del CRUD sobre SQLite en memoria.
 */
final class TodoCrudTest extends TestCase
{
    private Kernel \$kernel;

    protected function setUp(): void
    {
        \$connection = new Connection(['dsn' => 'sqlite::memory:']);
        \$connection->execute(
            'CREATE TABLE todos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(200) NOT NULL,
                done INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )'
        );

        \$manager = new DatabaseManager(['default' => 'test', 'connections' => ['test' => []]]);
        (new \\ReflectionProperty(DatabaseManager::class, 'connections'))->setValue(\$manager, ['test' => \$connection]);
        Model::setDbManager(\$manager);
        Model::flushMetadata();

        \$this->kernel = new Kernel(new Renderer([dirname(__DIR__, 2) . '/views']), ['debug' => false]);
    }

    private function request(string \$method, string \$path, string \$body = ''): \Dr2g\ErpLibs\Routing\Response
    {
        return \$this->kernel->handle(Request::create(\$method, \$path, body: \$body));
    }

    public function testCreateListShowToggleEditDelete(): void
    {
        \$created = \$this->request('POST', '/todos', '{"title":"Comprar pan"}');
        self::assertSame(303, \$created->status, 'redirige tras crear');
        self::assertSame('/todos', \$created->headers['Location']);

        \$list = \$this->request('GET', '/todos');
        self::assertSame(200, \$list->status);
        self::assertStringContainsString('Comprar pan', \$list->body);

        \$show = \$this->request('GET', '/todos/1');
        self::assertStringContainsString('Pendiente', \$show->body);

        \$toggle = \$this->request('POST', '/todos/1/toggle');
        self::assertSame(303, \$toggle->status);
        self::assertStringContainsString('Completada', \$this->request('GET', '/todos/1')->body);

        \$edit = \$this->request('POST', '/todos/1', '{"title":"Comprar pan integral"}');
        self::assertSame(303, \$edit->status);
        self::assertStringContainsString('Comprar pan integral', \$this->request('GET', '/todos/1')->body);

        \$delete = \$this->request('POST', '/todos/1/delete');
        self::assertSame(303, \$delete->status);
        self::assertStringContainsString('No hay tareas', \$this->request('GET', '/todos')->body);
    }

    public function testValidationErrorRendersFormWith422(): void
    {
        \$response = \$this->request('POST', '/todos', '{"title":"ab"}');

        self::assertSame(422, \$response->status);
        self::assertStringContainsString('at least 3 characters', \$response->body);
    }

    public function testUnknownTodoReturns404View(): void
    {
        \$response = \$this->request('GET', '/todos/99');

        self::assertSame(404, \$response->status);
        self::assertStringContainsString('Página no encontrada', \$response->body);
    }
}

PHP;
    }
}
