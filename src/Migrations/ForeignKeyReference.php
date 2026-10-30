<?php

namespace Dr2g\ErpLibs\Migrations;

class ForeignKeyReference
{
    private Blueprint $blueprint;
    private string $localColumn;
    private string $foreignColumn;

    public function __construct(Blueprint $blueprint, string $localColumn, string $foreignColumn)
    {
        $this->blueprint = $blueprint;
        $this->localColumn = $localColumn;
        $this->foreignColumn = $foreignColumn;
    }

    public function on(string $table): ForeignKeyConstraint
    {
        return new ForeignKeyConstraint(
            $this->blueprint,
            $this->localColumn,
            $table,
            $this->foreignColumn
        );
    }
}
