<?php

namespace Dr2g\ErpLibs\Core;

use Dr2g\ErpLibs\Security\SQLInjectionGuard;

class QueryBuilder
{
    /** @var list<string> */
    private array $wheres = [];
    /** @var list<mixed> */
    private array $bindings = [];
    /** @var list<string> */
    private array $orderBy = [];
    private ?int $limit = null;
    private ?int $offset = null;
    /** @var list<string> */
    private array $selects = ['*'];

    public function __construct(private readonly Connection $connection, private readonly string $table)
    {
        SQLInjectionGuard::assertIdentifier($table, 'table');
    }

    public function where(string $column, mixed $operator, mixed $value = null): self
    {
        $this->wheres[] = $this->buildCondition($column, $operator, $value, func_num_args());

        return $this;
    }

    /**
     * Builds one "column operator ?" fragment and appends its value to bindings.
     */
    private function buildCondition(string $column, mixed $operator, mixed $value, int $argumentCount): string
    {
        if ($argumentCount === 2) {
            $value = $operator;
            $operator = '=';
        }

        $column = SQLInjectionGuard::assertIdentifier($column, 'column');
        $operator = SQLInjectionGuard::assertOperator((string) $operator);

        if ($value === null) {
            return (str_starts_with($operator, 'IS') ? $column : "{$column} IS") . ' NULL';
        }

        $this->bindings[] = $value;

        return "{$column} {$operator} ?";
    }

    public function orWhere(string $column, mixed $operator, mixed $value = null): self
    {
        $clone = clone $this;

        $this->wheres[] = '(' . $this->buildCondition($column, $operator, $value, func_num_args()) . ')';

        return $this;
    }

    /**
     * @param list<mixed> $values
     */
    public function whereIn(string $column, array $values): self
    {
        $column = SQLInjectionGuard::assertIdentifier($column, 'column');

        if ($values === []) {
            $this->wheres[] = '1 = 0';
            return $this;
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = "{$column} IN ({$placeholders})";

        foreach ($values as $value) {
            $this->bindings[] = $value;
        }

        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $column = SQLInjectionGuard::assertIdentifier($column, 'column');
        $direction = SQLInjectionGuard::assertDirection($direction);

        $this->orderBy[] = "{$column} {$direction}";
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);
        return $this;
    }

    /**
     * @param list<string> $columns
     */
    public function select(array $columns): self
    {
        $this->selects = array_map(
            fn ($column) => SQLInjectionGuard::assertIdentifier($column, 'column'),
            $columns
        );

        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function get(): array
    {
        return $this->connection->select($this->toSql(), $this->bindings);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        $results = $this->limit(1)->get();
        return $results[0] ?? null;
    }

    public function count(): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}" . $this->whereClause();

        return (int) $this->connection->select($sql, $this->bindings)[0]['count'];
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function toSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->selects) . " FROM {$this->table}";

        $sql .= $this->whereClause();

        if (!empty($this->orderBy)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }

    /**
     * @return list<mixed>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    private function whereClause(): string
    {
        return empty($this->wheres) ? '' : ' WHERE ' . implode(' AND ', $this->wheres);
    }
}
