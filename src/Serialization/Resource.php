<?php

namespace Dr2g\ErpLibs\Serialization;

use Dr2g\ErpLibs\Models\Model;

/**
 * Converts models to arrays, honouring #[Hidden] attributes and selected relations.
 */
class Resource
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(private readonly array $values)
    {
    }

    /**
     * @param array<string, mixed> $includes relation name => list of models|array|null
     */
    public static function make(Model $model, array $includes = []): self
    {
        return new self(self::modelToArray($model, $includes));
    }

    /**
     * @param iterable<Model> $models
     * @param array<string, mixed> $includes
     * @return list<array<string, mixed>>
     */
    public static function collection(iterable $models, array $includes = []): array
    {
        $items = [];

        foreach ($models as $model) {
            $items[] = self::modelToArray($model, $includes);
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $includes
     * @return array<string, mixed>
     */
    private static function modelToArray(Model $model, array $includes): array
    {
        $data = array_diff_key($model->toArray(), array_flip($model->getHiddenAttributes()));

        foreach ($includes as $relation => $value) {
            $data[$relation] = $value instanceof Model
                ? self::modelToArray($value, [])
                : (is_array($value) ? array_map(static fn ($item) => $item instanceof Model ? self::modelToArray($item, []) : $item, $value) : $value);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    public function toJson(int $options = 0): string
    {
        return (string) json_encode($this->values, $options);
    }
}
