<?php

namespace Dr2g\ErpLibs\Migrations;

use Dr2g\ErpLibs\Core\Connection;
use ReflectionClass;

class MigrationManager
{
    private Connection $connection;
    private MigrationRepository $repository;
    private string $path;
    /** @var array<string, string> */
    private array $classes = [];

    public function __construct(Connection $connection, MigrationRepository $repository, ?string $path = null)
    {
        $this->connection = $connection;
        $this->repository = $repository;
        $this->path = rtrim($path ?? dirname(__DIR__, 2) . '/migrations', '/\\');
    }

    /** @return array<string, mixed> */
    public function migrate(?string $path = null): array
    {
        $path = rtrim($path ?? $this->path, '/\\');
        $migrations = $this->getPendingMigrations($path);

        if (empty($migrations)) {
            return ['status' => 'nothing_to_migrate'];
        }

        $batch = $this->repository->getNextBatchNumber();

        $this->connection->beginTransaction();

        try {
            $executed = [];

            foreach ($migrations as $migrationFile) {
                $migration = $this->loadMigration($migrationFile, $path);

                echo "Migrating: {$migrationFile}\n";

                $migration->up();

                $this->repository->markAsMigrated(
                    $migrationFile,
                    $batch,
                    date('Y-m-d H:i:s')
                );

                $executed[] = $migrationFile;
            }

            $this->connection->commit();

            return [
                'status' => 'success',
                'migrated' => $executed,
                'count' => count($executed),
                'batch' => $batch
            ];

        } catch (\Throwable $e) {
            $this->connection->rollBack();

            $hint = $this->connection->inTransaction()
                ? ''
                : ' Note: MySQL commits implicitly on DDL, so already executed statements may not be reverted.';

            throw new \Exception("Migration failed: {$e->getMessage()}." . $hint, 0, $e);
        }
    }

    /** @return array<string, mixed> */
    public function rollback(int $steps = 1): array
    {
        $lastMigrations = $this->repository->getLastMigrations($steps);

        if (empty($lastMigrations)) {
            return ['status' => 'nothing_to_rollback', 'rolled_back' => [], 'count' => 0];
        }

        $this->connection->beginTransaction();

        try {
            $rolledBack = [];

            foreach ($lastMigrations as $migrationRecord) {
                $migration = $this->loadMigration($migrationRecord['migration']);

                echo "Rolling back: {$migrationRecord['migration']}\n";

                $migration->down();

                $this->repository->removeMigration($migrationRecord['migration']);

                $rolledBack[] = $migrationRecord['migration'];
            }

            $this->connection->commit();

            return [
                'status' => 'success',
                'rolled_back' => $rolledBack,
                'count' => count($rolledBack)
            ];

        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw new \Exception("Rollback failed: {$e->getMessage()}. Rolled back.", 0, $e);
        }
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /** @return list<string> */
    private function getPendingMigrations(string $path): array
    {
        $files = glob("{$path}/*.php") ?: [];

        $allMigrations = array_map(fn ($f) => basename($f), $files);
        sort($allMigrations);

        return array_values(array_diff($allMigrations, $this->repository->getAllMigratedFiles()));
    }

    private function loadMigration(string $file, ?string $path = null): Migration
    {
        $path = $path ?? $this->path;
        $fullPath = is_file($file) ? $file : "{$path}/{$file}";

        if (!is_file($fullPath)) {
            throw new \RuntimeException("Migration file not found: {$fullPath}");
        }

        $className = $this->resolveClassName($fullPath);

        $schema = new SchemaBuilder($this->connection);

        /** @var Migration $migration */
        $migration = new $className($schema, $path);

        return $migration;
    }

    /**
     * Resolves the migration class declared in a file. Filenames are prefixed with
     * a timestamp, so the class name cannot be derived from the filename alone.
     */
    private function resolveClassName(string $fullPath): string
    {
        if (isset($this->classes[$fullPath])) {
            return $this->classes[$fullPath];
        }

        $before = get_declared_classes();

        require_once $fullPath;

        foreach (array_diff(get_declared_classes(), $before) as $class) {
            if ((new ReflectionClass($class))->isSubclassOf(Migration::class)) {
                return $this->classes[$fullPath] = $class;
            }
        }

        $declared = array_diff(get_declared_classes(), $before);

        if ($declared !== []) {
            return $this->classes[$fullPath] = reset($declared);
        }

        // Class already loaded by an earlier run: match by the file it was declared in.
        foreach (get_declared_classes() as $class) {
            $reflection = new ReflectionClass($class);

            if ($reflection->isSubclassOf(Migration::class) && $reflection->getFileName() === $fullPath) {
                return $this->classes[$fullPath] = $class;
            }
        }

        throw new \RuntimeException("No class found in migration file {$fullPath}.");
    }
}
