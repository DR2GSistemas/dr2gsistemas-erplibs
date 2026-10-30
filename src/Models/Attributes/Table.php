<?php

namespace Dr2g\ErpLibs\Models\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Table
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $connection = null
    ) {
    }
}
