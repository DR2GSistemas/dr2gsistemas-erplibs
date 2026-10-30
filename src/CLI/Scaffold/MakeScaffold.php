<?php

namespace Dr2g\ErpLibs\CLI\Scaffold;

/**
 * Plantillas de los comandos `make:*` (controller, model, view, route,
 * middleware, request, test).
 *
 * Cada plantilla recibe el contexto del proyecto y el nombre pedido, y devuelve
 * el contenido del archivo listo para escribir.
 */
final class MakeScaffold
{
    /**
     * `proyecto-items` / `ProyectoItems` -> `ProyectoItems`.
     */
    public static function studly(string $value): string
    {
        $parts = preg_split('/[^A-Za-z0-9]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $studly = implode('', array_map(static fn (string $part): string => ucfirst($part), $parts));

        return $studly === '' ? 'Item' : $studly;
    }

    /**
     * `ProyectoItems` -> `proyecto-items`; `User` -> `users`.
     */
    public static function snake(string $value): string
    {
        $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $value));

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $snake), '_') ?: 'item';
    }

    /**
     * `User` -> `users`; `Category` -> `categories`; `Address` -> `addresses`.
     */
    public static function table(string $value): string
    {
        $snake = self::snake($value);

        if (preg_match('/(s|x|z|ch|sh)$/', $snake) === 1) {
            return $snake . 'es';
        }

        if (preg_match('/[^aeiou]y$/', $snake) === 1) {
            return substr($snake, 0, -1) . 'ies';
        }

        return $snake . 's';
    }

    /**
     * Nombre de acción a partir de la entrada: admite `show` y `Users/Show`.
     */
    public static function action(string $value): string
    {
        $parts = preg_split('#[/\\\\]#', trim($value)) ?: [];
        $action = (string) end($parts);

        return lcfirst(self::studly($action));
    }

    /**
     * Namespace completo de una clase dentro del proyecto.
     */
    public static function namespace(ProjectContext $project, string $class): string
    {
        return $project->namespace . '\\' . str_replace('/', '\\', trim($class, '/'));
    }

    /**
     * Controlador web con una acción que renderiza su vista.
     *
     * @param string|null $view vista a renderizar; por defecto "<recurso>.<accion>"
     */
    public static function controller(ProjectContext $project, string $class, string $action, ?string $view = null): string
    {
        $namespace = self::namespace($project, 'Controllers');
        $view ??= self::snake(str_replace('Controller', '', $class)) . '.' . self::snake($action);

        return <<<PHP
<?php

namespace {$namespace};

use Dr2g\ErpLibs\Routing\Request;

/**
 * Controlador web. No hereda de Controller para poder recibir parámetros de
 * ruta (`show(Request \$request, int \$id)`); el router los inyecta por nombre.
 */
class {$class}
{
    public function {$action}(Request \$request): mixed
    {
        \$renderer = \$request->attribute('renderer');

        if (!\$renderer instanceof \Dr2g\ErpLibs\View\Renderer) {
            throw new \RuntimeException('Renderer no disponible en la petición.');
        }

        return \$renderer->layout('{$view}', 'layout', [
            'title' => '{$action}',
        ]);
    }
}
PHP;
    }

    /**
     * Controlador REST: index, show, store, update y destroy.
     */
    public static function resourceController(ProjectContext $project, string $class): string
    {
        $namespace = self::namespace($project, 'Controllers');

        return <<<PHP
<?php

namespace {$namespace};

use Dr2g\ErpLibs\Routing\Request;
use Dr2g\ErpLibs\Routing\Response;

/**
 * Controlador RESTful. El router inyecta `{id}` por nombre en las acciones con
 * parámetro.
 */
class {$class}
{
    public function index(Request \$request): mixed
    {
        return Response::text('Not implemented.', 501);
    }

    public function store(Request \$request): mixed
    {
        return Response::text('Not implemented.', 501);
    }

    public function show(Request \$request, int \$id): mixed
    {
        return Response::text('Not implemented.', 501);
    }

    public function update(Request \$request, int \$id): mixed
    {
        return Response::text('Not implemented.', 501);
    }

    public function destroy(Request \$request, int \$id): mixed
    {
        return Response::text('Not implemented.', 501);
    }
}
PHP;
    }

    /**
     * Controlador de API: extiende ApiCrud y sólo declara el contrato.
     */
    public static function apiController(ProjectContext $project, string $class, string $model): string
    {
        $namespace = self::namespace($project, 'Controllers/Api');
        $modelNamespace = 'App\\Models\\' . $model;
        $modelFqcn = $project->namespace . '\\Models\\' . $model;

        return <<<PHP
<?php

namespace {$namespace};

use Dr2g\\ErpLibs\\Api\\ApiCrud;
use {$modelFqcn};

/**
 * CRUD REST sobre el modelo {$model}: paginación, filtros, orden y validación
 * los aporta {@see ApiCrud}.
 */
final class {$class} extends ApiCrud
{
    protected function model(): string
    {
        return {$model}::class;
    }

    protected function rules(): array
    {
        return [
            // 'title' => ['required', 'max:200'],
        ];
    }

    protected function fillable(): array
    {
        return [];
    }

    protected function sortable(): array
    {
        return ['id'];
    }

    protected function filters(): array
    {
        return [];
    }
}
PHP;
    }

    /**
     * Modelo del ORM con la tabla derivada del nombre.
     */
    public static function model(ProjectContext $project, string $class, string $table): string
    {
        $namespace = self::namespace($project, 'Models');

        return <<<PHP
<?php

namespace {$namespace};

use Dr2g\ErpLibs\Models\Attributes\Column;
use Dr2g\ErpLibs\Models\Attributes\Table;
use Dr2g\ErpLibs\Models\Model;

#[Table(name: '{$table}')]
class {$class} extends Model
{
    #[Column(type: 'VARCHAR(200)', validation: ['required', 'max:200'])]
    public string \$name;
}
PHP;
    }

    /**
     * Vista PHP envuelta por el layout del proyecto.
     */
    public static function view(string $class, string $action, ?string $path = null): string
    {
        $name = self::snake($class) . '.' . self::snake($action);
        $file = $path ?? 'src/views/' . str_replace('.', '/', $name) . '.php';
        $heading = ucfirst(str_replace('_', ' ', self::snake($action)));

        return <<<PHP
<?php
/**
 * Vista {$name}. La envuelve views/layout.php mediante Renderer::layout().
 *
 * @var string \$title
 */
?>
<h1><?= htmlspecialchars(\$title ?? '{$heading}', ENT_QUOTES) ?></h1>
<p>edita este archivo en <code>{$file}</code></p>
PHP;
    }

    /**
     * Middleware compatible con `Router::use()`.
     */
    public static function middleware(ProjectContext $project, string $class): string
    {
        $namespace = self::namespace($project, 'Http/Middleware');

        return <<<PHP
<?php

namespace {$namespace};

use Dr2g\ErpLibs\Routing\Request;
use Dr2g\ErpLibs\Routing\Response;

/**
 * Middleware: recibe la petición y `\$next`, y devuelve la Response.
 */
class {$class}
{
    public function __invoke(Request \$request, callable \$next): Response
    {
        \$response = \$next(\$request);

        return \$response;
    }
}
PHP;
    }

    /**
     * Form Request: validación declarativa de entrada.
     */
    public static function request(ProjectContext $project, string $class): string
    {
        $namespace = self::namespace($project, 'Http/Requests');

        return <<<PHP
<?php

namespace {$namespace};

use Dr2g\\ErpLibs\\Routing\\Request;
use Dr2g\\ErpLibs\\Validation\\Validator;

/**
 * Reglas de validación para la entrada de la petición.
 */
class {$class}
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // 'name' => 'required|max:200',
        ];
    }

    /**
     * Valida la petición y devuelve los datos limpios.
     *
     * @return array<string, mixed>
     */
    public function validate(Request \$request): array
    {
        return Validator::validate(\$request->input(), \$this->rules());
    }
}
PHP;
    }

    /**
     * Ruta registrada sobre el kernel del proyecto.
     */
    public static function route(string $entries): string
    {
        return <<<PHP
<?php

/*
 * Rutas registradas con `make:route`. El kernel carga todos los archivos de
 * src/routes; añade aquí las nuevas rutas de este recurso.
 *
 * @var \Dr2g\ErpLibs\Routing\Kernel \$kernel
 */

{$entries}
PHP;
    }

    /**
     * Subview: fragmento reutilizable incluido con `$subview()`.
     */
    public static function subview(string $name, string $path): string
    {
        $partial = 'partials.' . $name;
        $label = ucfirst(str_replace(['.', '_'], ' ', $name));

        return <<<PHP
<?php
/**
 * Subview {$partial}. Se incluye desde otra vista con:
 *
 *     <?= \$subview('{$partial}', ['label' => '...']) ?>
 *
 * También puede usarse desde un controlador: \$renderer->subview('{$partial}').
 *
 * Archivo: {$path}
 *
 * @var string \$label  texto visible (por defecto el nombre de la subview)
 * @var string \$class  clases CSS opcionales
 */
\$label = isset(\$label) ? (string) \$label : '{$label}';
\$class = isset(\$class) ? (string) \$class : '';
?>
<span class="subview <?= htmlspecialchars(\$class, ENT_QUOTES) ?>"><?= htmlspecialchars(\$label, ENT_QUOTES) ?></span>
PHP;
    }

    /**
     * Repositorio que extiende la base de la librería y declara el modelo.
     */
    public static function repository(ProjectContext $project, string $class, string $model): string
    {
        $namespace = self::namespace($project, 'Repositories');
        $modelFqcn = $project->namespace . '\\Models\\' . $model;

        return <<<PHP
<?php

namespace {$namespace};

use Dr2g\ErpLibs\Repositories\Repository;
use {$modelFqcn};

/**
 * Acceso a datos de {$model}. Hereda find, findOrFail, all, paginate, create,
 * update, delete y count; añade aquí las consultas propias del dominio.
 */
final class {$class} extends Repository
{
    protected function model(): string
    {
        return {$model}::class;
    }
}
PHP;
    }

    /**
     * Test PHPUnit vacío en el namespace AppTests.
     */
    public static function test(string $class): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace AppTests;

use PHPUnit\Framework\TestCase;

final class {$class} extends TestCase
{
    public function testExample(): void
    {
        self::assertTrue(true);
    }
}
PHP;
    }
}
