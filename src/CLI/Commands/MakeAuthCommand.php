<?php

namespace Dr2g\ErpLibs\CLI\Commands;

use Dr2g\ErpLibs\CLI\Command;
use Dr2g\ErpLibs\CLI\Scaffold\AuthScaffold;
use Dr2g\ErpLibs\CLI\Scaffold\ProjectContext;

/**
 * `make:auth [--no-register] [--force] [--path=<proyecto>]`
 *
 * Genera autenticación con RBAC: modelos, repositorios, migración, seeder,
 * middleware de sesión y CSRF, controladores, rutas, vistas y tests.
 */
class MakeAuthCommand extends Command
{
    public function getName(): string
    {
        return 'make:auth';
    }

    public function getDescription(): string
    {
        return 'Create authentication with roles and permissions (RBAC)';
    }

    /** @param list<string> $arguments */
    public function handle(array $arguments): void
    {
        $path = null;
        $force = false;

        foreach ($arguments as $argument) {
            match (true) {
                str_starts_with($argument, '--path=') => $path = substr($argument, 7),
                $argument === '--force' => $force = true,
                $argument === '--no-register' => null,
                str_starts_with($argument, '--') => throw new \InvalidArgumentException("Opción desconocida: {$argument}"),
                default => null,
            };
        }

        $project = ProjectContext::detect($path);
        $files = AuthScaffold::files($project->namespace);

        // Un `--force` basta para sobrescribir todos los artefactos.
        foreach ($files as $relative => $content) {
            $target = $project->path($relative);

            if (is_file($target) && !$force) {
                throw new \RuntimeException(
                    "Ya existe {$relative}. Usa --force para sobrescribir."
                );
            }
        }

        foreach ($files as $relative => $content) {
            $target = $project->path($relative);
            $directory = dirname($target);

            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new \RuntimeException("No se pudo crear el directorio {$directory}.");
            }

            file_put_contents($target, $content);

            echo "[OK] creado: {$relative}\n";
        }

        $this->printNextSteps($project);
    }

    private function printNextSteps(ProjectContext $project): void
    {
        echo "\nSiguientes pasos:\n";
        echo "  1. Registra la sesión y el middleware en bootstrap.php:\n";
        echo "       \$session = new Dr2g\\ErpLibs\\Auth\\Session\\Session();\n";
        echo "       \$session->start();\n";
        echo "       \$hash   = new Dr2g\\ErpLibs\\Auth\\Password\\Hasher();\n";
        echo "       \$auth   = new Dr2g\\ErpLibs\\Auth\\AuthManager(\$session, fn (\$id) => (new \\{$project->namespace}\\Repositories\\UserRepository())->find((int) \$id));\n";
        echo "       \$gate   = new Dr2g\\ErpLibs\\Auth\\Gate();\n";
        echo "  2. Ejecuta la migración y el seeder:\n";
        echo "       php bin/app migrate\n";
        echo "       php bin/app db:seed\n";
        echo "  3. Entra en /login con INSTALL_ADMIN_EMAIL/INSTALL_ADMIN_PASSWORD del .env\n";
    }
}
