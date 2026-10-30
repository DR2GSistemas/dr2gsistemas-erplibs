<?php

namespace Dr2g\ErpLibs\CLI\Commands;

use Dr2g\ErpLibs\CLI\Scaffold\MakeScaffold;
use Dr2g\ErpLibs\CLI\Scaffold\ProjectContext;

/**
 * `make:request <Nombre> [--force]`
 *
 * Genera un objeto de validación en `src/Http/Requests`, con `rules()` y
 * `validate(Request)` sobre `Validator`.
 */
class MakeRequestCommand extends MakeCommand
{
    public function getName(): string
    {
        return 'make:request';
    }

    public function getDescription(): string
    {
        return 'Create a form request (validation rules)';
    }

    protected function usage(): string
    {
        return 'Usage: make:request <Name> [--force]';
    }

    protected function label(): string
    {
        return 'Request';
    }

    protected function target(ProjectContext $project, string $name, array $options): string
    {
        return 'src/Http/Requests/' . $this->className($name) . '.php';
    }

    protected function content(ProjectContext $project, string $name, array $options): string
    {
        return MakeScaffold::request($project, $this->className($name));
    }

    private function className(string $name): string
    {
        $class = MakeScaffold::studly($name);

        return str_ends_with($class, 'Request') ? $class : $class . 'Request';
    }
}
