<?php

namespace Dr2g\ErpLibs\Models\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class PrimaryKey
{
    public function __construct(
        public readonly bool $autoIncrement = true
    ) {
    }
}
