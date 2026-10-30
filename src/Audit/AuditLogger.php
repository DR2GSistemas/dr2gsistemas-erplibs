<?php

namespace Dr2g\ErpLibs\Audit;

use Dr2g\ErpLibs\Events\Dispatcher;
use Dr2g\ErpLibs\Models\Model;

/**
 * Listens to model lifecycle events and records who changed what.
 */
class AuditLogger
{
    /** @var array<class-string, list<string>> */
    private static array $sensitive = [];

    private ?\Closure $actor;

    /**
     * @param null|callable(): (string|int|null) $actor
     */
    public function __construct(
        private readonly AuditStorageInterface $storage,
        ?callable $actor = null
    ) {
        $this->actor = $actor === null ? null : \Closure::fromCallable($actor);
    }

    /**
     * @param callable(): (string|int|null) $resolver
     */
    public function setActor(callable $resolver): void
    {
        $this->actor = \Closure::fromCallable($resolver);
    }

    /**
     * Marks attributes that must never be written to the audit log.
     *
     * @param class-string $model
     * @param list<string> $attributes
     */
    public static function markSensitive(string $model, array $attributes): void
    {
        self::$sensitive[$model] = $attributes;
    }

    /**
     * Wires the logger onto a dispatcher so create/update/delete are audited.
     */
    public function attach(Dispatcher $dispatcher): self
    {
        foreach (['created' => 'create', 'updated' => 'update', 'deleted' => 'delete'] as $event => $action) {
            $dispatcher->listen($event, function (Model $model) use ($action): void {
                $this->record($action, $model);
            });
        }

        return $this;
    }

    /**
     * @param array<string, mixed> $changes defaults to the model attributes
     */
    public function record(string $action, Model $model, array $changes = []): void
    {
        $attributes = $model->getAttributes();

        $this->storage->store([
            'action' => $action,
            'model' => $model::class,
            'table' => $model->getTableName(),
            'id' => $attributes[$model::getPrimaryKey()] ?? null,
            'changes' => $this->redact($model::class, $changes !== [] ? $changes : $attributes),
            'actor' => $this->actor === null ? null : ($this->actor)(),
            'at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private function redact(string $model, array $changes): array
    {
        foreach (self::$sensitive[$model] ?? [] as $attribute) {
            if (array_key_exists($attribute, $changes)) {
                $changes[$attribute] = '[redacted]';
            }
        }

        return $changes;
    }
}
