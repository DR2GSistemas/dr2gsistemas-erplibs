<?php

namespace Dr2g\ErpLibs\Core;

use Dr2g\ErpLibs\Exceptions\DatabaseException;
use PDO;
use PDOException;

class Connection
{
    private readonly PDO $pdo;
    /** @var array<string, mixed> */
    private readonly array $config;
    private ?\Dr2g\ErpLibs\Log\Logger $logger = null;
    private float $slowQueryThreshold = 0.1;

    /** @param array<string, mixed> $config */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->pdo = $this->createPDO();
    }

    /**
     * Any query slower than $seconds is logged at warning level with its SQL.
     */
    public function setSlowQueryLogging(\Dr2g\ErpLibs\Log\Logger $logger, float $seconds = 0.1): void
    {
        $this->logger = $logger;
        $this->slowQueryThreshold = $seconds;
    }

    private function createPDO(): PDO
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        // A full DSN overrides host/port/database (useful for tests and sockets).
        if (isset($this->config['dsn'])) {
            try {
                return new PDO(
                    (string) $this->config['dsn'],
                    $this->config['username'] ?? null,
                    $this->config['password'] ?? null,
                    $options
                );
            } catch (PDOException $e) {
                throw new DatabaseException('Connection failed: ' . $e->getMessage(), 0, $e);
            }
        }

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config['driver'],
            $this->config['host'],
            $this->config['port'] ?? 3306,
            $this->config['database'],
            $this->config['charset'] ?? 'utf8mb4'
        );

        try {
            return new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $options
            );
        } catch (PDOException $e) {
            throw new DatabaseException('Connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        if (!$this->pdo->inTransaction()) {
            return false;
        }

        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        // DDL statements (CREATE/ALTER/DROP TABLE) cause an implicit commit in MySQL,
        // so there may be no active transaction left to roll back.
        if (!$this->pdo->inTransaction()) {
            return false;
        }

        return $this->pdo->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function getDriver(): string
    {
        if (isset($this->config['driver']) && $this->config['driver'] !== '') {
            return (string) $this->config['driver'];
        }

        // La conexión SQLite del proyecto sólo declara `dsn`; sin esta
        // deducción, las migraciones no detectaban el motor y usaban el DDL
        // de MySQL.
        $dsn = (string) ($this->config['dsn'] ?? '');
        $scheme = str_contains($dsn, ':') ? strstr($dsn, ':', true) : false;

        return $scheme === false ? '' : strtolower($scheme);
    }

    /** @param list<mixed> $bindings */
    public function execute(string $sql, array $bindings = []): int|false
    {
        $start = microtime(true);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);

        $this->logIfSlow($start, $sql);

        return $stmt->rowCount();
    }

    /**
     * @param list<mixed> $bindings
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        $start = microtime(true);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        $this->logIfSlow($start, $sql);

        return $rows;
    }

    private function logIfSlow(float $start, string $sql): void
    {
        if ($this->logger === null) {
            return;
        }

        $elapsed = microtime(true) - $start;

        if ($elapsed >= $this->slowQueryThreshold) {
            $this->logger->warning('Slow query {elapsed}s: {sql}', [
                'elapsed' => round($elapsed, 4),
                'sql' => preg_replace('/\s+/', ' ', trim($sql)),
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";

        $this->execute($sql, array_values($data));

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     * @param list<mixed> $whereBindings
     */
    public function update(string $table, array $data, string $where, array $whereBindings = []): int
    {
        $setParts = [];
        foreach ($data as $column => $value) {
            $setParts[] = "{$column} = ?";
        }
        $setClause = implode(', ', $setParts);

        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";

        $bindings = array_merge(array_values($data), $whereBindings);

        return (int) $this->execute($sql, $bindings);
    }

    /** @param list<mixed> $bindings */
    public function delete(string $table, string $where, array $bindings = []): int
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return (int) $this->execute($sql, $bindings);
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
