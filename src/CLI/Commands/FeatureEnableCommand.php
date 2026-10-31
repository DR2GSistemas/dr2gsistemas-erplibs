<?php

namespace Dr2g\ErpLibs\CLI\Commands;

use Dr2g\ErpLibs\CLI\Command;
use Dr2g\ErpLibs\CLI\Scaffold\ProjectContext;
use Dr2g\ErpLibs\CLI\Scaffold\VueScaffold;

/**
 * `feature:enable vue|material|primevue [--path=<proyecto>]`
 */
final class FeatureEnableCommand extends Command
{
    public function getName(): string
    {
        return 'feature:enable';
    }

    public function getDescription(): string
    {
        return 'Enable Vue, Material CSS, or PrimeVue in a generated app';
    }

    /** @param list<string> $arguments */
    public function handle(array $arguments): void
    {
        $feature = null;
        $path = null;

        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--path=')) {
                $path = substr($argument, 7);

                continue;
            }

            if (str_starts_with($argument, '--')) {
                throw new \InvalidArgumentException("Opción desconocida: {$argument}");
            }

            if ($feature !== null) {
                throw new \InvalidArgumentException('Indica un solo feature por ejecución.');
            }

            $feature = $argument;
        }

        if ($feature === null) {
            echo "Usage: feature:enable vue|material|primevue [--path=<proyecto>]\n";

            return;
        }

        $project = ProjectContext::detect($path);
        $files = (new VueScaffold())->enable($project, $feature);

        if ($files === []) {
            echo "[OK] feature ya habilitado: {$feature}\n";

            return;
        }

        foreach ($files as $file) {
            echo "[OK] actualizado: {$file}\n";
        }

        echo "\nSiguiente paso:\n";
        echo "  npm install\n";
        echo "  npm run dr2g:dev\n";
    }
}
