<?php

namespace Dr2g\ErpLibs\Migrations;

use Dr2g\ErpLibs\Core\Connection;

class SchemaBuilder
{
    private Connection $connection;
    /** @var list<array<string, mixed>> */
    private array $operations = [];

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        $this->operations[] = [
            'type' => 'create',
            'table' => $table,
            'columns' => $blueprint->getColumns(),
            'indexes' => $blueprint->getIndexes(),
            'foreignKeys' => $blueprint->getForeignKeys()
        ];

        $this->executeCreateTable(
            $table,
            $blueprint->getColumns(),
            $blueprint->getIndexes(),
            $blueprint->getForeignKeys()
        );
    }

    public function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        $this->operations[] = [
            'type' => 'alter',
            'table' => $table,
            'columns' => $blueprint->getColumns(),
            'indexes' => $blueprint->getIndexes(),
            'foreignKeys' => $blueprint->getForeignKeys()
        ];

        $this->executeAlterTable(
            $table,
            $blueprint->getColumns(),
            $blueprint->getIndexes(),
            $blueprint->getForeignKeys()
        );
    }

    public function drop(string $table): void
    {
        $this->operations[] = ['type' => 'drop', 'table' => $table];

        $this->executeDropTable($table);
    }

    /**
     * The operations recorded so far (kept for introspection/tests and dry-run).
     */
    /** @return list<array<string, mixed>> */
    public function getOperations(): array
    {
        return $this->operations;
    }

    /**
     * Conexión que recibe las sentencias DDL.
     */
    public function connection(): Connection
    {
        return $this->connection;
    }

    public function resetOperations(): void
    {
        $this->operations = [];
    }

    /**
     * @param list<array<string, mixed>> $columns
     * @param list<array<string, mixed>> $indexes
     * @param list<ForeignKeyConstraint> $foreignKeys
     */
    private function executeCreateTable(
        string $table,
        array $columns,
        array $indexes,
        array $foreignKeys
    ): void {
        $columnDefs = [];

        foreach ($columns as $column) {
            $def = "{$column['name']} {$column['type']}";

            if (!$column['nullable']) {
                $def .= ' NOT NULL';
            }

            if (isset($column['default'])) {
                $def .= ' DEFAULT ' . $this->renderDefault($column['default']);
            }

            if ($column['autoIncrement'] ?? false) {
                $def .= ' AUTO_INCREMENT PRIMARY KEY';
            }

            $columnDefs[] = $def;
        }

        foreach ($indexes as $index) {
            $columns_str = implode(', ', $index['columns']);

            $indexDef = match($index['type']) {
                'unique' => "UNIQUE INDEX {$index['name']} ({$columns_str})",
                'fulltext' => "FULLTEXT INDEX {$index['name']} ({$columns_str})",
                'spatial' => "SPATIAL INDEX {$index['name']} ({$columns_str})",
                default => "INDEX {$index['name']} ({$columns_str})"
            };

            $columnDefs[] = $indexDef;
        }

        foreach ($foreignKeys as $fk) {
            $columnDefs[] = "CONSTRAINT fk_{$table}_{$fk->localColumn} FOREIGN KEY ({$fk->localColumn}) " .
                            "REFERENCES {$fk->foreignTable}({$fk->foreignColumn}) " .
                            "ON DELETE {$fk->onDelete} " .
                            "ON UPDATE {$fk->onUpdate}";
        }

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (\n    " .
               implode(",\n    ", $columnDefs) .
               "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $this->connection->execute($sql);
    }

    /**
     * @param list<array<string, mixed>> $columns
     * @param list<array<string, mixed>> $indexes
     * @param list<ForeignKeyConstraint> $foreignKeys
     */
    private function executeAlterTable(
        string $table,
        array $columns,
        array $indexes,
        array $foreignKeys
    ): void {
        foreach ($columns as $column) {
            $sql = "ALTER TABLE {$table} ADD COLUMN {$column['name']} {$column['type']}";

            if (!$column['nullable']) {
                $sql .= ' NOT NULL';
            }

            if (isset($column['default'])) {
                $sql .= ' DEFAULT ' . $this->renderDefault($column['default']);
            }

            if ($column['autoIncrement'] ?? false) {
                $sql .= ' AUTO_INCREMENT PRIMARY KEY';
            }

            $this->connection->execute($sql);
        }

        foreach ($indexes as $index) {
            $columns_str = implode(', ', $index['columns']);

            $indexType = match($index['type']) {
                'unique' => 'UNIQUE ',
                'fulltext' => 'FULLTEXT ',
                'spatial' => 'SPATIAL ',
                default => ''
            };

            $this->connection->execute(
                "CREATE {$indexType}INDEX {$index['name']} ON {$table} ({$columns_str})"
            );
        }

        foreach ($foreignKeys as $fk) {
            $this->connection->execute(
                "ALTER TABLE {$table} ADD CONSTRAINT fk_{$table}_{$fk->localColumn} " .
                "FOREIGN KEY ({$fk->localColumn}) REFERENCES {$fk->foreignTable}({$fk->foreignColumn}) " .
                "ON DELETE {$fk->onDelete} ON UPDATE {$fk->onUpdate}"
            );
        }
    }

    private function renderDefault(mixed $default): string
    {
        return match(true) {
            is_bool($default) => $default ? 'TRUE' : 'FALSE',
            is_int($default), is_float($default) => (string) $default,
            $default === null => 'NULL',
            default => "'" . addslashes((string) $default) . "'"
        };
    }

    private function executeDropTable(string $table): void
    {
        $this->connection->execute("DROP TABLE IF EXISTS {$table}");
    }
}
