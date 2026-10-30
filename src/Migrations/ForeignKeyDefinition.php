<?php

namespace Dr2g\ErpLibs\Migrations;

class ForeignKeyDefinition
{
    public function __construct(
        public readonly Blueprint $blueprint,
        public readonly string $column
    ) {
    }

    public function references(string $column): ForeignKeyReference
    {
        return new ForeignKeyReference($this->blueprint, $this->column, $column);
    }
}
