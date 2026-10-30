<?php

namespace Dr2g\ErpLibs\CLI\Commands;

use Dr2g\ErpLibs\CLI\Scaffold\MakeScaffold;
use Dr2g\ErpLibs\CLI\Scaffold\ProjectContext;

/**
 * `make:repository <Nombre> [--model=Nombre] [--force]`
 *
 * Genera un repositorio en `src/Repositories` que extiende la base de la
 * librería y declara el modelo asociado.
 */
class MakeRepositoryCommand extends MakeCommand
{
    public function getName(): string
    {
        return 'make:repository';
    }

    public function getDescription(): string
    {
        return 'Create a repository (data access) for a model';
    }

    protected function usage(): string
    {
        return 'Usage: make:repository <Name> [--model=Name] [--force]';
    }

    protected function label(): string
    {
        return 'Repository';
    }

    protected function target(ProjectContext $project, string $name, array $options): string
    {
        return 'src/Repositories/' . $this->className($name) . '.php';
    }

    protected function content(ProjectContext $project, string $name, array $options): string
    {
        $model = MakeScaffold::studly((string) $this->option($options, 'model', $this->modelFromName($name)));

        return MakeScaffold::repository($project, $this->className($name), $model);
    }

    /**
     * `ProductRepository` / `Product` -> `Product`.
     */
    private function modelFromName(string $name): string
    {
        return (string) preg_replace('/Repository$/', '', MakeScaffold::studly($name));
    }

    private function className(string $name): string
    {
        $class = MakeScaffold::studly($name);

        return str_ends_with($class, 'Repository') ? $class : $class . 'Repository';
    }
}
