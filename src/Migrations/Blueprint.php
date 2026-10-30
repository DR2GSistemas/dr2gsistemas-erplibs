<?php

namespace Dr2g\ErpLibs\Migrations;

class Blueprint
{
    private string $table;
    /** @var list<array<string, mixed>> */
    private array $columns = [];
    /** @var list<array<string, mixed>> */
    private array $indexes = [];
    /** @var list<ForeignKeyConstraint> */
    private array $foreignKeys = [];

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function id(): self
    {
        $this->columns[] = [
            'name' => 'id',
            'type' => 'BIGINT UNSIGNED',
            'autoIncrement' => true,
            'nullable' => false
        ];
        return $this;
    }

    public function string(string $name, int $length = 255): self
    {
        $this->columns[] = [
            'name' => $name,
            'type' => "VARCHAR({$length})",
            'nullable' => false
        ];
        return $this;
    }

    public function text(string $name): self
    {
        $this->columns[] = [
            'name' => $name,
            'type' => 'TEXT',
            'nullable' => false
        ];
        return $this;
    }

    public function integer(string $name): self
    {
        $this->columns[] = [
            'name' => $name,
            'type' => 'INTEGER',
            'nullable' => false
        ];
        return $this;
    }

    public function bigInteger(string $name): self
    {
        $this->columns[] = [
            'name' => $name,
            'type' => 'BIGINT',
            'nullable' => false
        ];
        return $this;
    }

    public function unsignedBigInteger(string $name): self
    {
        $this->columns[] = [
            'name' => $name,
            'type' => 'BIGINT UNSIGNED',
            'nullable' => false
        ];
        return $this;
    }

    public function decimal(string $name, int $total = 8, int $places = 2): self
    {
        $this->columns[] = [
            'name' => $name,
            'type' => "DECIMAL({$total}, {$places})",
            'nullable' => false
        ];
        return $this;
    }

    public function boolean(string $name): self
    {
        $this->columns[] = [
            'name' => $name,
            'type' => 'BOOLEAN',
            'nullable' => false,
            'default' => false
        ];
        return $this;
    }

    public function json(string $name): self
    {
        $this->columns[] = [
            'name' => $name,
            'type' => 'JSON',
            'nullable' => false
        ];
        return $this;
    }

    public function dateTime(string $name): self
    {
        $this->columns[] = [
            'name' => $name,
            'type' => 'DATETIME',
            'nullable' => false
        ];
        return $this;
    }

    public function timestamp(string $name): self
    {
        $this->columns[] = [
            'name' => $name,
            'type' => 'TIMESTAMP',
            'nullable' => false
        ];
        return $this;
    }

    public function timestamps(): self
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
        return $this;
    }

    public function softDeletes(): self
    {
        $this->timestamp('deleted_at')->nullable();
        return $this;
    }

    public function nullable(): self
    {
        $index = count($this->columns) - 1;
        $this->columns[$index]['nullable'] = true;
        return $this;
    }

    public function default(mixed $value): self
    {
        $index = count($this->columns) - 1;
        $this->columns[$index]['default'] = $value;
        return $this;
    }

    /** @param list<string>|string|null $columns */
    public function unique(array|string|null $columns = null, ?string $name = null): self
    {
        if ($columns === null) {
            return $this->uniqueIndex([$this->lastColumnName()], $name);
        }

        return $this->uniqueIndex((array) $columns, $name);
    }

    /** @param list<string> $columns */
    public function uniqueIndex(array $columns, ?string $name = null): self
    {
        return $this->addIndex('unique', $columns, $name);
    }

    /** @param list<string>|string|null $columns */
    public function index(array|string|null $columns = null, ?string $name = null): self
    {
        if ($columns === null) {
            return $this->addIndex('index', [$this->lastColumnName()], $name);
        }

        return $this->addIndex('index', (array) $columns, $name);
    }

    /** @param list<string> $columns */
    public function fullText(array $columns, ?string $name = null): self
    {
        return $this->addIndex('fulltext', $columns, $name);
    }

    /** @param list<string> $columns */
    private function addIndex(string $type, array $columns, ?string $name): self
    {
        $suffix = in_array($type, ['unique', 'fulltext'], true) ? $type : 'index';
        $name = $name ?? "{$this->table}_" . implode('_', $columns) . "_{$suffix}";

        foreach ($this->indexes as $index) {
            if ($index['name'] === $name && $index['type'] === $type) {
                return $this;
            }
        }

        $this->indexes[] = [
            'type' => $type,
            'columns' => $columns,
            'name' => $name
        ];

        return $this;
    }

    public function foreignId(string $name): ForeignKeyDefinition
    {
        $this->unsignedBigInteger($name);
        return new ForeignKeyDefinition($this, $name);
    }

    public function foreignIdFor(string $name): self
    {
        $this->unsignedBigInteger($name);
        return $this;
    }

    private function lastColumnName(): string
    {
        if (empty($this->columns)) {
            throw new \LogicException('Modifier must follow a column definition.');
        }

        return $this->columns[array_key_last($this->columns)]['name'];
    }

    public function addForeignKey(ForeignKeyConstraint $constraint): void
    {
        $this->foreignKeys[] = $constraint;
    }

    /** @return list<array<string, mixed>> */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /** @return list<array<string, mixed>> */
    public function getIndexes(): array
    {
        return $this->indexes;
    }

    /** @return list<ForeignKeyConstraint> */
    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }
}
