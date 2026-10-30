<?php

namespace Dr2g\ErpLibs\CLI\Commands;

use Dr2g\ErpLibs\CLI\Scaffold\MakeScaffold;
use Dr2g\ErpLibs\CLI\Scaffold\ProjectContext;

/**
 * `make:controller <Nombre> [--resource] [--api] [--force]`
 *
 * Genera un controlador en `src/Controllers`. Con `--resource` crea las cinco
 * acciones RESTful; con `--api` genera un CRUD sobre `ApiCrud` en
 * `src/Controllers/Api`.
 */
class MakeControllerCommand extends MakeCommand
{
    public function getName(): string
    {
        return 'make:controller';
    }

    public function getDescription(): string
    {
        return 'Create a controller (optionally --resource or --api + Model)';
    }

    protected function usage(): string
    {
        return 'Usage: make:controller <Name> [--resource] [--api] [--force]';
    }

    protected function label(): string
    {
        return 'Controller';
    }

    protected function target(ProjectContext $project, string $name, array $options): string
    {
        $class = $this->className($name);

        return $this->flag($options, 'api')
            ? "src/Controllers/Api/{$class}.php"
            : "src/Controllers/{$class}.php";
    }

    protected function content(ProjectContext $project, string $name, array $options): string
    {
        $class = $this->className($name);

        if ($this->flag($options, 'api')) {
            $model = MakeScaffold::studly((string) $this->option($options, 'model', MakeScaffold::studly($name)));

            return MakeScaffold::apiController($project, $class, $model);
        }

        if ($this->flag($options, 'resource')) {
            return MakeScaffold::resourceController($project, $class);
        }

        $action = MakeScaffold::action((string) $this->option($options, 'action', 'index'));

        // La vista se deriva del nombre pedido (pluralizable), no del class:
        // `make:controller products` debe ver `products.index`, no `product.index`.
        $resource = MakeScaffold::snake($name);
        $view = $this->option($options, 'view', $resource . '.' . MakeScaffold::snake($action));

        return MakeScaffold::controller($project, $class, $action, $view);
    }

    /**
     * `users` / `UserController` -> `UserController`.
     */
    private function className(string $name): string
    {
        $class = MakeScaffold::studly($name);

        return str_ends_with($class, 'Controller') ? $class : $class . 'Controller';
    }
}
