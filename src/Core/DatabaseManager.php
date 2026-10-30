<?php

namespace Dr2g\ErpLibs\Core;

class DatabaseManager
{
    /** @var array<string, Connection> */
    private array $connections = [];
    /** @var array<string, mixed> */
    private readonly array $config;

    /** @param array<string, mixed> $config */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getConnection(string $name = 'default'): Connection
    {
        $resolved = $name === 'default'
            ? ($this->config['default'] ?? 'default')
            : $name;

        if (!isset($this->connections[$resolved])) {
            $connectionConfig = $this->config['connections'][$resolved]
                ?? $this->config['connections'][$name]
                ?? throw new \InvalidArgumentException("Database connection [{$resolved}] is not configured.");

            $this->connections[$resolved] = new Connection($connectionConfig);
        }

        return $this->connections[$resolved];
    }

    public function disconnect(string $name = 'default'): void
    {
        $resolved = $name === 'default' ? ($this->config['default'] ?? 'default') : $name;

        unset($this->connections[$resolved]);
    }
}
