<?php

namespace Dr2g\ErpLibs\CLI\Commands;

use Dr2g\ErpLibs\CLI\Scaffold\MakeScaffold;
use Dr2g\ErpLibs\CLI\Scaffold\ProjectContext;

/**
 * `make:test <Nombre> [--feature] [--force]`
 *
 * Genera un test PHPUnit en `src/tests/Unit` (o `src/tests/Feature`), con el
 * mismo namespace `AppTests` que usan los tests del scaffold.
 */
class MakeTestCommand extends MakeCommand
{
    public function getName(): string
    {
        return 'make:test';
    }

    public function getDescription(): string
    {
        return 'Create a PHPUnit test (--feature for Feature/)';
    }

    protected function usage(): string
    {
        return 'Usage: make:test <Name> [--feature] [--force]';
    }

    protected function label(): string
    {
        return 'Test';
    }

    protected function target(ProjectContext $project, string $name, array $options): string
    {
        $directory = $this->flag($options, 'feature') ? 'Feature' : 'Unit';

        return "src/tests/{$directory}/" . $this->className($name) . '.php';
    }

    protected function content(ProjectContext $project, string $name, array $options): string
    {
        return MakeScaffold::test($this->className($name));
    }

    private function className(string $name): string
    {
        $class = MakeScaffold::studly($name);

        return str_ends_with($class, 'Test') ? $class : $class . 'Test';
    }
}
