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

        $config = require 'config/database.php';

        $dbManager = new DatabaseManager($config);
        $connection = $dbManager->getConnection();

        $repository = new MigrationRepository($connection);
        $manager = new MigrationManager($connection, $repository, $path);

        $result = $manager->migrate();

        if ($result['status'] === 'nothing_to_migrate') {
            echo "Nothing to migrate.\n";
        } else {
            echo "[OK] Migrated {$result['count']} migration(s) in batch {$result['batch']}.\n";
        }
    }
}
