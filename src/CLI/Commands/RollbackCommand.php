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

        $config = require 'config/database.php';

        $dbManager = new DatabaseManager($config);
        $connection = $dbManager->getConnection();

        $repository = new MigrationRepository($connection);
        $manager = new MigrationManager($connection, $repository, $path);

        $result = $manager->rollback($steps);

        if ($result['status'] === 'nothing_to_rollback') {
            echo "Nothing to rollback.\n";
            return;
        }

        echo "[OK] Rolled back {$result['count']} migration(s).\n";
    }
}
