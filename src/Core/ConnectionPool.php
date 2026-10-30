<?php

namespace Dr2g\ErpLibs\Core;

/**
 * Fixed-size pool that reuses connections instead of reconnecting per request
 * path. Only Connection objects are managed; callers check them out and return
 * them so the pool can enforce the size limit.
 */
class ConnectionPool
{
    /** @var list<Connection> */
    private array $idle = [];

    private int $leased = 0;

    /**
     * @param \Closure(): Connection $factory
     */
    public function __construct(
        private \Closure $factory,
        private readonly int $maxConnections = 5
    ) {
        $this->factory = \Closure::fromCallable($factory);
    }

    public function acquire(): Connection
    {
        if ($this->idle !== []) {
            $this->leased++;
            return array_pop($this->idle);
        }

        if ($this->leased >= $this->maxConnections) {
            throw new \RuntimeException("Connection pool exhausted ({$this->maxConnections} connections).");
        }

        $connection = ($this->factory)();

        if (!$this->healthy($connection)) {
            throw new \RuntimeException('New connection failed its health check.');
        }

        $this->leased++;

        return $connection;
    }

    public function release(Connection $connection): void
    {
        $this->leased = max(0, $this->leased - 1);

        if ($this->healthy($connection)) {
            $this->idle[] = $connection;
        }
    }

    public function inUse(): int
    {
        return $this->leased;
    }

    public function idleCount(): int
    {
        return count($this->idle);
    }

    /**
     * A connection is healthy when it can answer the cheapest possible query.
     */
    private function healthy(Connection $connection): bool
    {
        try {
            $connection->select('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
