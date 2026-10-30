<?php

namespace Dr2g\ErpLibs\CLI\Commands;

use Dr2g\ErpLibs\CLI\Command;
use Dr2g\ErpLibs\CLI\Scaffold\AppGenerator;

/**
 * `new:app <directorio> [--namespace=Acme] [--name="Mi ERP"] [--force] [--dry-run]`
 *
 * Genera un proyecto con route, controller y views conectados al kernel.
 */
class NewAppCommand extends Command
{
    public function getName(): string
    {
        return 'new:app';
    }

    public function getDescription(): string
    {
        return 'Scaffold a new app (route + controller + views)';
    }

    /** @param list<string> $arguments */
    public function handle(array $arguments): void
    {
        $path = null;
        $namespace = 'App';
        $name = null;
        $force = false;
        $dryRun = false;

        foreach ($arguments as $argument) {
            match (true) {
                str_starts_with($argument, '--namespace=') => $namespace = substr($argument, 12),
                str_starts_with($argument, '--name=') => $name = substr($argument, 7),
                $argument === '--force' => $force = true,
                $argument === '--dry-run' => $dryRun = true,
                str_starts_with($argument, '--') => throw new \InvalidArgumentException("Opción desconocida: {$argument}"),
                default => $path = $argument,
            };
        }

        if ($path === null) {
            echo "Usage: new:app <directory> [--namespace=Acme] [--name=\"Mi ERP\"] [--force] [--dry-run]\n";
            return;
        }

        $base = rtrim(str_replace('\\', '/', $path), '/');
        $generator = new AppGenerator($base);

        $created = $generator->generate($namespace, $name, ['force' => $force, 'dryRun' => $dryRun]);

        $prefix = $dryRun ? '[DRY-RUN] would create' : '[OK] created';

        foreach ($created as $file) {
            echo "{$prefix} {$base}/{$file}\n";
        }

        if ($dryRun) {
            return;
        }

        echo "\nNext steps:\n";
        echo "  cd {$base}\n";
        echo "  composer install\n";
        echo "  php -S localhost:8080 -t public public/index.php\n";
        echo "  open http://localhost:8080\n";
    }
}
