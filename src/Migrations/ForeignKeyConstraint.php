<?php

namespace Dr2g\ErpLibs\Migrations;

class ForeignKeyConstraint
{
    public function __construct(
        public readonly Blueprint $blueprint,
        public readonly string $localColumn,
        public readonly string $foreignTable,
        public readonly string $foreignColumn,
        public string $onDelete = 'RESTRICT',
        public string $onUpdate = 'RESTRICT'
    ) {
    }

    public function cascadeOnDelete(): self
    {
        $this->onDelete = 'CASCADE';
        return $this;
    }

    public function restrictOnDelete(): self
    {
        $this->onDelete = 'RESTRICT';
        return $this;
    }

    public function setNullOnDelete(): self
    {
        $this->onDelete = 'SET NULL';
        return $this;
    }

    public function cascadeOnUpdate(): self
    {
        $this->onUpdate = 'CASCADE';
        return $this;
    }

    public function constrain(): Blueprint
    {
        $this->blueprint->addForeignKey($this);
        return $this->blueprint;
    }

    /**
     * The blueprint instance (allows chaining foreign key modifiers mid-definition).
     */
    public function blueprint(): Blueprint
    {
        return $this->blueprint;
    }
}
