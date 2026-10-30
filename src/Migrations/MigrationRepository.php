<?php

namespace Dr2g\ErpLibs\Migrations;

use Dr2g\ErpLibs\Core\Connection;

class MigrationRepository
{
    private Connection $connection;
    private string $table = 'migrations';

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->ensureTableExists();
    }

    private function ensureTableExists(): void
    {
        $this->connection->execute("
            CREATE TABLE IF NOT EXISTS {$this->table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INT NOT NULL,
                migrated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    public function markAsMigrated(string $file, int $batch, string $timestamp): void
    {
        $this->connection->execute(
            "INSERT INTO {$this->table} (migration, batch, migrated_at) VALUES (?, ?, ?)",
            [$file, $batch, $timestamp]
        );
    }

    /**
     * @param list<string> $allMigrations
     * @return list<string>
     */
    public function getPendingMigrations(array $allMigrations): array
    {
        $migrated = $this->getAllMigratedFiles();
        return array_values(array_diff($allMigrations, $migrated));
    }

    /** @return list<string> */
    public function getAllMigratedFiles(): array
    {
        $results = $this->connection->select(
            "SELECT migration FROM {$this->table}"
        );

        return array_column($results, 'migration');
    }

    /** @return list<array<string, mixed>> */
    public function getLastMigrations(int $steps): array
    {
        $lastBatch = $this->getLastBatchNumber();

        if ($lastBatch === null) {
            return [];
        }

        return $this->connection->select(
            "SELECT * FROM {$this->table} WHERE batch >= ? ORDER BY id DESC",
            [max(1, $lastBatch - $steps + 1)]
        );
    }

    public function removeMigration(string $file): void
    {
        $this->connection->execute(
            "DELETE FROM {$this->table} WHERE migration = ?",
            [$file]
        );
    }

    public function getNextBatchNumber(): int
    {
        return ($this->getLastBatchNumber() ?? 0) + 1;
    }

    private function getLastBatchNumber(): ?int
    {
        $result = $this->connection->select(
            "SELECT MAX(batch) as max_batch FROM {$this->table}"
        );

        $max = $result[0]['max_batch'] ?? null;

        return $max === null ? null : (int) $max;
    }

    /** @return list<array<string, mixed>> */
    public function getAllMigrations(): array
    {
        return $this->connection->select("SELECT * FROM {$this->table}");
    }

    public function connection(): Connection
    {
        return $this->connection;
    }
}
