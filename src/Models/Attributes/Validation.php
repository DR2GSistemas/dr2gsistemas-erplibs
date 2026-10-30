<?php

namespace Dr2g\ErpLibs\Models\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_CLASS)]
class Validation
{
    public function __construct(
        /** @var list<string> */
        public readonly array $rules = [],
        /** @var array<string, string> */
        public readonly array $messages = []
    ) {
    }
}
