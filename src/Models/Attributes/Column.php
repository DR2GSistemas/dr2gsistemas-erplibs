<?php

namespace Dr2g\ErpLibs\Models\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Column
{
    public function __construct(
        public readonly string $type,
        public readonly bool $nullable = false,
        public readonly mixed $default = null,
        public readonly bool $unique = false,
        public readonly bool $index = false,
        /** @var list<string> */
        public readonly array $validation = []
    ) {
    }
}
