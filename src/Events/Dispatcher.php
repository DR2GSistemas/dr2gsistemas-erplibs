<?php

namespace Dr2g\ErpLibs\Events;

/**
 * Minimal pub/sub: listeners are plain callables keyed by event name.
 */
class Dispatcher
{
    /** @var array<string, list<callable>> */
    private array $listeners = [];

    public function listen(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    /**
     * Runs every listener until one returns false (cancellation).
     *
     * @param array<int, mixed> $payload
     */
    public function dispatch(string $event, array $payload = []): bool
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            if ($listener(...$payload) === false) {
                return false;
            }
        }

        return true;
    }

    public function forget(string $event): void
    {
        unset($this->listeners[$event]);
    }

    /**
     * @return list<callable>
     */
    public function getListeners(string $event): array
    {
        return $this->listeners[$event] ?? [];
    }
}
