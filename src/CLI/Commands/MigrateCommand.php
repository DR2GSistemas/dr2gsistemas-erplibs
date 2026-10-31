<?php

namespace Dr2g\ErpLibs\CLI\Commands;

use Dr2g\ErpLibs\CLI\Command;
use Dr2g\ErpLibs\Core\DatabaseManager;
use Dr2g\ErpLibs\Migrations\MigrationManager;
use Dr2g\ErpLibs\Migrations\MigrationRepository;

class MigrateCommand extends Command
{
    public function getName(): string
    {
        return 'migrate';
    }

    public function getDescription(): string
    {
        return 'Run pending migrations';
    }

    /** @param list<string> $arguments */
    public function handle(array $arguments): void
    {
        $path = null;

        foreach ($arguments as $arg) {
            if (!str_starts_with($arg, '--')) {
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

        $result = $manager->migrate();

        if ($result['status'] === 'nothing_to_migrate') {
            echo "Nothing to migrate.\n";
        } else {
            echo "[OK] Migrated {$result['count']} migration(s) in batch {$result['batch']}.\n";
        }
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
