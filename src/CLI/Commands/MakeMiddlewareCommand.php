<?php

namespace Dr2g\ErpLibs\CLI\Commands;

use Dr2g\ErpLibs\CLI\Scaffold\MakeScaffold;
use Dr2g\ErpLibs\CLI\Scaffold\ProjectContext;

/**
 * `make:middleware <Nombre> [--force]`
 *
 * Genera un middleware invocable en `src/Http/Middleware`, listo para
 * `$kernel->router()->use(new Nombre())`.
 */
class MakeMiddlewareCommand extends MakeCommand
{
    public function getName(): string
    {
        return 'make:middleware';
    }

    public function getDescription(): string
    {
        return 'Create an HTTP middleware';
    }

    protected function usage(): string
    {
        return 'Usage: make:middleware <Name> [--force]';
    }

    protected function label(): string
    {
        return 'Middleware';
    }

    protected function target(ProjectContext $project, string $name, array $options): string
    {
        return 'src/Http/Middleware/' . MakeScaffold::studly($name) . '.php';
    }

    protected function content(ProjectContext $project, string $name, array $options): string
    {
        return MakeScaffold::middleware($project, MakeScaffold::studly($name));
    }
}
