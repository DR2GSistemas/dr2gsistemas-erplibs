<?php

namespace Dr2g\ErpLibs\Models;

use Dr2g\ErpLibs\Core\DatabaseManager;
use Dr2g\ErpLibs\Core\QueryBuilder;
use Dr2g\ErpLibs\Events\Dispatcher;
use Dr2g\ErpLibs\Models\Attributes\Column;
use Dr2g\ErpLibs\Models\Attributes\Hidden;
use Dr2g\ErpLibs\Models\Attributes\PrimaryKey;
use Dr2g\ErpLibs\Models\Attributes\Table;
use Dr2g\ErpLibs\Validation\Validator;
use ReflectionClass;

/**
 * Attribute-driven model. Rows live in $attributes so that schema columns the
 * model does not declare (id, timestamps, soft deletes) still work.
 */
abstract class Model
{
    protected static ?DatabaseManager $dbManager = null;

    protected static ?Dispatcher $dispatcher = null;

    /** @var array<string, list<string>> */
    private static array $validationRules = [];

    /** @var array<string, list<string>> */
    private static array $hiddenAttributes = [];

    /** @var array<string, mixed> */
    protected array $attributes = [];

    /** @var array<string, mixed> */
    protected array $original = [];

    protected bool $exists = false;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    public static function setDbManager(DatabaseManager $manager): void
    {
        self::$dbManager = $manager;
    }

    public static function setEventDispatcher(?Dispatcher $dispatcher): void
    {
        self::$dispatcher = $dispatcher;
    }

    public static function getEventDispatcher(): Dispatcher
    {
        return self::$dispatcher ??= new Dispatcher();
    }

    public static function query(): QueryBuilder
    {
        return new QueryBuilder(static::getConnection(), static::getTableName());
    }

    public static function getTableName(): string
    {
        $attributes = (new ReflectionClass(static::class))->getAttributes(Table::class);

        if ($attributes !== []) {
            return $attributes[0]->newInstance()->name;
        }

        $className = (new ReflectionClass(static::class))->getShortName();

        return strtolower($className) . 's';
    }

    public static function getPrimaryKey(): string
    {
        foreach ((new ReflectionClass(static::class))->getProperties() as $property) {
            if ($property->getAttributes(PrimaryKey::class) !== []) {
                return $property->getName();
            }
        }

        return 'id';
    }

    public static function usesSoftDeletes(): bool
    {
        foreach ((new ReflectionClass(static::class))->getProperties() as $property) {
            if ($property->getAttributes(Column::class) !== []
                && $property->getName() === 'deleted_at') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<static>
     */
    public static function all(): array
    {
        $query = static::query();

        if (static::usesSoftDeletes()) {
            $query->where('deleted_at', null);
        }

        return static::hydrateMany($query->get());
    }

    public static function find(int|string $id): ?static
    {
        $row = static::query()->where(static::getPrimaryKey(), $id)->first();

        return $row === null ? null : static::hydrate($row);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function create(array $attributes): static
    {
        /** @var static $instance */
        $instance = new static();
        $instance->fill($attributes);
        $instance->save();

        return $instance;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<static>
     */
    public static function hydrateMany(array $rows): array
    {
        return array_map(static fn (array $row) => static::hydrate($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function hydrate(array $row): static
    {
        /** @var static $instance */
        $instance = new static();
        $instance->attributes = $row;
        $instance->original = $row;
        $instance->exists = true;
        $instance->fill($row);

        return $instance;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;

            // Typed properties without a default must be initialised before access.
            if (property_exists($this, $key) && $value !== null) {
                try {
                    $this->{$key} = $value;
                } catch (\TypeError) {
                    // Keep the row value; the declared property type is narrower.
                }
            }
        }

        return $this;
    }

    public function save(): bool
    {
        $this->validate();

        $dispatcher = static::getEventDispatcher();
        $isNew = !$this->exists;

        if (!$dispatcher->dispatch('saving', [$this])
            || !$dispatcher->dispatch($isNew ? 'creating' : 'updating', [$this])) {
            return false;
        }

        $saved = $isNew ? $this->insert() : $this->update();

        if ($saved) {
            $dispatcher->dispatch($isNew ? 'created' : 'updated', [$this]);
        }

        return $saved;
    }

    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $dispatcher = static::getEventDispatcher();

        if (!$dispatcher->dispatch('deleting', [$this])) {
            return false;
        }

        $key = static::getPrimaryKey();
        $connection = static::getConnection();

        if (static::usesSoftDeletes()) {
            $affected = $connection->update(
                static::getTableName(),
                ['deleted_at' => date('Y-m-d H:i:s')],
                "{$key} = ?",
                [$this->attributes[$key]]
            );
        } else {
            $affected = $connection->delete(static::getTableName(), "{$key} = ?", [$this->attributes[$key]]);
        }

        if ($affected > 0) {
            $this->exists = false;
            $dispatcher->dispatch('deleted', [$this]);

            return true;
        }

        return false;
    }

    public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        }

        // Declared properties are plain PHP properties, not columns: fall back to them.
        try {
            return $this->{$name};
        } catch (\Error $error) {
            // Uninitialised typed property: an attribute may hold the value.
            if (property_exists($this, $name) && $this->attributes !== []) {
                return $this->attributes[$name] ?? null;
            }

            throw $error;
        }
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * @return list<string>
     */
    public function getHiddenAttributes(): array
    {
        return self::$hiddenAttributes[static::class] ??= self::reflectHiddenAttributes();
    }

    /**
     * Rules declared through #[Column(validation: [...])], keyed by attribute.
     *
     * @return array<string, list<string>>
     */
    public static function getValidationRules(): array
    {
        if (!isset(self::$validationRules[static::class])) {
            /** @var list<string> $rules */
            $rules = self::reflectValidationRules();
            self::$validationRules[static::class] = $rules;
        }

        /** @var array<string, list<string>> */
        return self::$validationRules[static::class];
    }

    protected function validate(): void
    {
        $rules = static::getValidationRules();

        if ($rules !== []) {
            Validator::assert($this->attributes, $rules);
        }
    }

    /**
     * Drop cached attribute metadata (rules, hidden) when reloading classes.
     */
    public static function flushMetadata(): void
    {
        self::$hiddenAttributes = [];
        self::$validationRules = [];
    }

    /**
     * @return list<string>
     */
    private static function reflectHiddenAttributes(): array
    {
        $hidden = [];

        foreach ((new ReflectionClass(static::class))->getProperties() as $property) {
            if ($property->getAttributes(Hidden::class) !== []) {
                $hidden[] = $property->getName();
            }
        }

        return $hidden;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function reflectValidationRules(): array
    {
        $rules = [];

        foreach ((new ReflectionClass(static::class))->getProperties() as $property) {
            $attributes = $property->getAttributes(Column::class);

            if ($attributes === []) {
                continue;
            }

            $declared = $attributes[0]->newInstance()->validation;

            if ($declared !== []) {
                $rules[$property->getName()] = $declared;
            }
        }

        /** @var array<string, list<string>> $rules */
        return $rules;
    }

    protected function insert(): bool
    {
        $data = $this->getDirty();

        if ($data === []) {
            return true;
        }

        $id = static::getConnection()->insert(static::getTableName(), $data);

        $key = static::getPrimaryKey();

        if (!isset($this->attributes[$key])) {
            $this->attributes[$key] = $id;
        }

        $this->original = $this->attributes;
        $this->exists = true;

        return true;
    }

    protected function update(): bool
    {
        $key = static::getPrimaryKey();
        $data = $this->getDirty();

        unset($data[$key]);

        if ($data === []) {
            return true;
        }

        $affected = static::getConnection()->update(
            static::getTableName(),
            $data,
            "{$key} = ?",
            [$this->attributes[$key] ?? null]
        );

        $this->original = $this->attributes;

        return $affected > 0;
    }

    protected static function getConnection(): \Dr2g\ErpLibs\Core\Connection
    {
        if (self::$dbManager === null) {
            throw new \RuntimeException('No database manager configured. Call Model::setDbManager() first.');
        }

        return self::$dbManager->getConnection();
    }
}
