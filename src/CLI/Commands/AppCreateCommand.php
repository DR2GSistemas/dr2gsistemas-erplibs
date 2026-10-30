<?php

namespace Dr2g\ErpLibs\CLI\Commands;

use Dr2g\ErpLibs\CLI\Command;
use Dr2g\ErpLibs\CLI\Scaffold\AppGenerator;

/**
 * `app:create <nombre> [--api] [--crud=todo] [--namespace=Acme] [--force] [--dry-run]`
 *
 * Crea `<nombre>/` en el directorio actual con el scaffold conectado a la
 * librería (ruta + controller + vistas) y, opcionalmente, un CRUD de prueba.
 */
class AppCreateCommand extends Command
{
    public function getName(): string
    {
        return 'app:create';
    }

    public function getDescription(): string
    {
        return 'Create an app skeleton (route + controller + views), optionally with a demo CRUD';
    }

    /** @param list<string> $arguments */
    public function handle(array $arguments): void
    {
        $name = null;
        $namespace = null;
        $crud = null;
        $root = getenv('DR2G_PROJECT_ROOT') ?: dirname(__DIR__, 5);
        $api = false;
        $force = false;
        $dryRun = false;

        foreach ($arguments as $argument) {
            match (true) {
                str_starts_with($argument, '--namespace=') => $namespace = substr($argument, 12),
                str_starts_with($argument, '--crud=') => $crud = substr($argument, 7),
                str_starts_with($argument, '--path=') => $root = substr($argument, 7),
                $argument === '--api' => $api = true,
                $argument === '--force' => $force = true,
                $argument === '--dry-run' => $dryRun = true,
                str_starts_with($argument, '--') => throw new \InvalidArgumentException("Opción desconocida: {$argument}"),
                default => $name = $argument,
            };
        }

        if ($name === null || $name === '') {
            echo "Usage: app:create <name> [--api] [--crud=todo] [--namespace=Acme] [--force] [--dry-run]\n";
            return;
        }

        $slug = preg_replace('/[^A-Za-z0-9._-]/', '', $name) ?: 'app';

        $target = rtrim($root, '/\\') . '/' . $slug;

        $generator = new AppGenerator($target);

        $created = $generator->generate($namespace, $name, [
            'force' => $force,
            'dryRun' => $dryRun,
            'crud' => $crud,
            'api' => $api,
        ]);

        $prefix = $dryRun ? '[DRY-RUN] would create' : '[OK] created';

        foreach ($created as $file) {
            echo "{$prefix} {$slug}/{$file}\n";
        }

        if ($dryRun) {
            return;
        }

        echo "\nNext steps:\n";
        echo "  cd {$slug}\n";
        echo "  composer install\n";
        echo "  php bin/app migrate          # crea la tabla todos\n";
        echo "  php -S localhost:8080 -t public public/index.php\n";
        echo "  open http://localhost:8080/todos\n";
    }
}
