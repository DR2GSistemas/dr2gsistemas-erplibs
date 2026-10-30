<?php

namespace Dr2g\ErpLibs\CLI\Commands;

use Dr2g\ErpLibs\CLI\Scaffold\MakeScaffold;
use Dr2g\ErpLibs\CLI\Scaffold\ProjectContext;

/**
 * `make:model <Nombre> [--table=nombre] [--force]`
 *
 * Genera un modelo del ORM en `src/Models`, con la tabla derivada del nombre
 * (`User` -> `users`, `Category` -> `categories`).
 */
class MakeModelCommand extends MakeCommand
{
    public function getName(): string
    {
        return 'make:model';
    }

    public function getDescription(): string
    {
        return 'Create an ORM model';
    }

    protected function usage(): string
    {
        return 'Usage: make:model <Name> [--table=name] [--force]';
    }

    protected function label(): string
    {
        return 'Model';
    }

    protected function target(ProjectContext $project, string $name, array $options): string
    {
        return 'src/Models/' . MakeScaffold::studly($name) . '.php';
    }

    protected function content(ProjectContext $project, string $name, array $options): string
    {
        $class = MakeScaffold::studly($name);
        $table = $this->option($options, 'table', MakeScaffold::table($class));

        return MakeScaffold::model($project, $class, (string) $table);
    }
}
