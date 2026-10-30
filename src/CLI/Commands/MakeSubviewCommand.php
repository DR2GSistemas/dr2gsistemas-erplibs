<?php

namespace Dr2g\ErpLibs\CLI\Commands;

use Dr2g\ErpLibs\CLI\Scaffold\MakeScaffold;
use Dr2g\ErpLibs\CLI\Scaffold\ProjectContext;

/**
 * `make:subview <nombre> [--force]`
 *
 * Genera una subview (fragmento reutilizable) en `src/views/partials`. Se
 * incluye en otra vista con `<?= $subview('partials.badge', [...]) ?>`.
 */
class MakeSubviewCommand extends MakeCommand
{
    public function getName(): string
    {
        return 'make:subview';
    }

    public function getDescription(): string
    {
        return 'Create a reusable subview inside src/views/partials';
    }

    protected function usage(): string
    {
        return 'Usage: make:subview <name> [--force]     (ej.: badge o users/row)';
    }

    protected function label(): string
    {
        return 'Subview';
    }

    protected function target(ProjectContext $project, string $name, array $options): string
    {
        return $this->relative($name);
    }

    protected function content(ProjectContext $project, string $name, array $options): string
    {
        $view = $this->viewName($name);

        return MakeScaffold::subview($view, $this->relative($name));
    }

    /**
     * Nombre de subview sin el prefijo `partials.`: `partials.badge` -> `badge`.
     */
    private function viewName(string $name): string
    {
        $segments = array_values(array_filter(
            explode('.', str_replace(['\\', '/'], '.', $name)),
            static fn (string $segment): bool => $segment !== ''
        ));

        $segments = array_map(static fn (string $segment): string => MakeScaffold::snake($segment), $segments);

        if (($segments[0] ?? null) === 'partials') {
            array_shift($segments);
        }

        return implode('.', $segments !== [] ? $segments : ['subview']);
    }

    private function relative(string $name): string
    {
        return 'src/views/partials/' . str_replace('.', '/', $this->viewName($name)) . '.php';
    }
}
