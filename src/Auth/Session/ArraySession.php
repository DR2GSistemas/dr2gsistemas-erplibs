<?php

namespace Dr2g\ErpLibs\Auth\Session;

/**
 * Sesión en memoria: para tests y para ejecutar la app sin estado entre
 * peticiones.
 */
final class ArraySession implements SessionInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    private string $id = '';

    private bool $started = false;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function start(): void
    {
        if (!$this->started) {
            $this->started = true;
            $this->id = $this->id !== '' ? $this->id : bin2hex(random_bytes(16));
        }
    }

    public function getId(): string
    {
        $this->start();

        return $this->id;
    }

    public function regenerate(bool $deleteOldData = false): bool
    {
        $this->start();

        if ($deleteOldData) {
            $this->data = [];
        }

        $this->id = bin2hex(random_bytes(16));

        return true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    public function flush(): void
    {
        $this->data = [];
    }

    public function isStarted(): bool
    {
        return $this->started;
    }
}
