<?php

namespace Dr2g\ErpLibs\CLI\Commands;

use Dr2g\ErpLibs\CLI\Command;
use Dr2g\ErpLibs\Core\DatabaseManager;
use Dr2g\ErpLibs\Migrations\MigrationManager;
use Dr2g\ErpLibs\Migrations\MigrationRepository;

class RollbackCommand extends Command
{
    public function getName(): string
    {
        return 'migrate:rollback';
    }

    public function getDescription(): string
    {
        return 'Rollback the last migration batch';
    }

    /** @param list<string> $arguments */
    public function handle(array $arguments): void
    {
        $steps = 1;
        $path = null;

        foreach ($arguments as $arg) {
            if (str_starts_with($arg, '--steps=')) {
                $steps = max(1, (int) substr($arg, 8));
            } elseif (!str_starts_with($arg, '--')) {
                $path = $arg;
            }
        }

        $config = require $this->resolveConfigFile();

        $dbManager = new DatabaseManager($config);
        $connection = $dbManager->getConnection();

        $repository = new MigrationRepository($connection);
        // Sin ruta explícita se usa `migrations/` del proyecto actual, no la de
        // la librería.
        $manager = new MigrationManager($connection, $repository, $path ?? 'migrations');

        $result = $manager->rollback($steps);

        if ($result['status'] === 'nothing_to_rollback') {
            echo "Nothing to rollback.\n";
            return;
        }

        echo "[OK] Rolled back {$result['count']} migration(s).\n";
    }

    /**
     * Ubica la configuración de base de datos: `src/config/database.php` en los
     * proyectos actuales, `config/database.php` en los anteriores.
     */
    private function resolveConfigFile(): string
    {
        foreach (['src/config/database.php', 'config/database.php'] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('No se encontró src/config/database.php. Ejecuta el comando desde la raíz del proyecto.');
    }
}
