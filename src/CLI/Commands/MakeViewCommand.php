<?php

namespace Dr2g\ErpLibs\CLI\Commands;

use Dr2g\ErpLibs\CLI\Scaffold\MakeScaffold;
use Dr2g\ErpLibs\CLI\Scaffold\ProjectContext;

/**
 * `make:view <vista> [--force]`
 *
 * Genera una vista PHP en `src/views`. El nombre usa jerarquía solo con punto
 * o barra: `users.show` / `users/show` -> `src/views/users/show.php`.
 */
class MakeViewCommand extends MakeCommand
{
    public function getName(): string
    {
        return 'make:view';
    }

    public function getDescription(): string
    {
        return 'Create a PHP view inside src/views';
    }

    protected function usage(): string
    {
        return 'Usage: make:view <name> [--force]     (ej.: users.show o users/show)';
    }

    protected function label(): string
    {
        return 'View';
    }

    protected function target(ProjectContext $project, string $name, array $options): string
    {
        return $this->relative($name);
    }

    protected function content(ProjectContext $project, string $name, array $options): string
    {
        $parts = explode('.', $this->renderable($name));
        $action = (string) array_pop($parts);
        $class = implode('', array_map(static fn (string $part): string => MakeScaffold::studly($part), $parts)) ?: 'Home';

        return MakeScaffold::view($class, $action, $this->relative($name));
    }

    /**
     * Ruta relativa de la vista. Renderer resuelve `users.show` a
     * views/users/show.php: el archivo usa barra, nunca punto.
     */
    private function relative(string $name): string
    {
        return 'src/views/' . str_replace('.', '/', $this->renderable($name)) . '.php';
    }

    /**
     * `users/show` -> `users.show`.
     */
    private function renderable(string $name): string
    {
        $segments = array_values(array_filter(
            explode('.', str_replace(['\\', '/'], '.', $name)),
            static fn (string $segment): bool => $segment !== ''
        ));

        return implode('.', array_map(static fn (string $segment): string => MakeScaffold::snake($segment), $segments));
    }
}
