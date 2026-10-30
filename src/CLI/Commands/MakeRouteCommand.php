<?php

namespace Dr2g\ErpLibs\CLI\Commands;

use Dr2g\ErpLibs\CLI\Scaffold\MakeScaffold;
use Dr2g\ErpLibs\CLI\Scaffold\ProjectContext;

/**
 * `make:route <uri> [--controller=Nombre@accion] [--method=get] [--file=api] [--force]`
 *
 * Añade un archivo de rutas en `src/routes/` con la ruta registrada. No pisa
 * `web.php`: cada archivo nuevo se registra solo desde el kernel.
 */
class MakeRouteCommand extends MakeCommand
{
    public function getName(): string
    {
        return 'make:route';
    }

    public function getDescription(): string
    {
        return 'Create a routes file with a registered route';
    }

    protected function usage(): string
    {
        return 'Usage: make:route <uri> [--controller=Name@action] [--method=get|post] [--file=<nombre>] [--force]';
    }

    protected function label(): string
    {
        return 'Route';
    }

    protected function target(ProjectContext $project, string $name, array $options): string
    {
        return 'src/routes/' . $this->fileName($name, $options) . '.php';
    }

    protected function content(ProjectContext $project, string $name, array $options): string
    {
        $method = strtolower((string) $this->option($options, 'method', 'get'));
        $controller = (string) $this->option($options, 'controller', MakeScaffold::studly($name) . 'Controller@index');
        $uri = '/' . ltrim($name, '/');

        $handler = str_contains($controller, '@')
            ? $project->namespace . '\\Controllers\\' . str_replace('/', '\\', $controller)
            : $project->namespace . '\\Controllers\\' . $controller . '@index';

        return MakeScaffold::route("\$kernel->router()->{$method}('{$uri}', '{$handler}');");
    }

    /**
     * `users` -> `users`; `users.api` -> `users_api`.
     *
     * @param array<string, string|bool> $options
     */
    private function fileName(string $name, array $options): string
    {
        $file = $this->option($options, 'file');

        return MakeScaffold::snake($file ?? $name);
    }
}
