<?php

namespace Dr2g\ErpLibs\Models\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class HasMany
{
    public function __construct(
        public readonly string $related,
        public readonly ?string $foreignKey = null,
        public readonly ?string $localKey = 'id'
    ) {
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class BelongsTo
{
    public function __construct(
        public readonly string $related,
        public readonly ?string $foreignKey = null,
        public readonly ?string $ownerKey = 'id'
    ) {
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class HasOne
{
    public function __construct(
        public readonly string $related,
        public readonly ?string $foreignKey = null,
        public readonly ?string $localKey = 'id'
    ) {
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ManyToMany
{
    public function __construct(
        public readonly string $related,
        public readonly ?string $table = null,
        public readonly ?string $foreignPivotKey = null,
        public readonly ?string $relatedPivotKey = null
    ) {
    }
}
