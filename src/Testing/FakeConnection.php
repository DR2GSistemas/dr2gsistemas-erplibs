<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Testing;

use Dr2g\ErpLibs\Core\Connection;

/**
 * Doble de Connection en memoria: registra el SQL ejecutado y responde lecturas
 * desde conjuntos configurados, para probar migraciones y modelos sin servidor.
 */
final class FakeConnection extends Connection
{
    /** @var list<string> */
    public array $executed = [];

    /** @var list<array{table: string, data: array<string, mixed>}> */
    public array $inserts = [];

    public int $affectedRows = 1;

    /**
     * @param array{all?: list<array<string, mixed>>, max_batch?: int|null, last_insert_id?: int} $selectResults
     */
    public function __construct(private array $selectResults = [])
    {
    }

    public function execute(string $sql, array $bindings = []): int
    {
        $this->executed[] = $this->normalize($sql);

        return $this->affectedRows;
    }

    public function select(string $sql, array $bindings = []): array
    {
        if (str_contains($sql, 'MAX(batch)')) {
            return [['max_batch' => $this->selectResults['max_batch'] ?? null]];
        }

        return $this->selectResults['all'] ?? [];
    }

    public function insert(string $table, array $data): int
    {
        $this->inserts[] = ['table' => $table, 'data' => $data];

        return (int) ($this->selectResults['last_insert_id'] ?? 1);
    }

    public function update(string $table, array $data, string $where, array $whereBindings = []): int
    {
        $this->executed[] = $this->normalize('UPDATE ' . $table . ' SET ' . implode(', ', array_keys($data)) . ' WHERE ' . $where);

        return $this->affectedRows;
    }

    public function delete(string $table, string $where, array $bindings = []): int
    {
        $this->executed[] = $this->normalize('DELETE FROM ' . $table . ' WHERE ' . $where);

        return $this->affectedRows;
    }

    public function beginTransaction(): bool
    {
        return true;
    }

    public function commit(): bool
    {
        return true;
    }

    public function rollBack(): bool
    {
        return true;
    }

    public function inTransaction(): bool
    {
        return false;
    }

    public function getPdo(): \PDO
    {
        throw new \LogicException('FakeConnection no tiene una instancia PDO.');
    }

    public function ddl(): string
    {
        return implode("\n", $this->executed);
    }

    private function normalize(string $sql): string
    {
        return (string) preg_replace('/\s+/', ' ', trim($sql));
    }
}
