<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Core;

use Dr2g\ErpLibs\Core\Connection;
use Dr2g\ErpLibs\Core\ConnectionPool;
use PHPUnit\Framework\TestCase;

final class HealthyConnection extends Connection
{
    public function __construct(private readonly bool $healthy = true)
    {
    }

    public function select(string $sql, array $bindings = []): array
    {
        if (!$this->healthy) {
            throw new \RuntimeException('down');
        }

        return [['1' => 1]];
    }
}

final class ConnectionPoolTest extends TestCase
{
    public function testReusesReleasedConnections(): void
    {
        $created = 0;
        $pool = new ConnectionPool(function () use (&$created) {
            $created++;
            return new HealthyConnection();
        }, maxConnections: 2);

        $first = $pool->acquire();
        $pool->release($first);
        $second = $pool->acquire();

        self::assertSame($first, $second);
        self::assertSame(1, $created);
        self::assertSame(1, $pool->inUse());
    }

    public function testThrowsWhenPoolIsExhausted(): void
    {
        $pool = new ConnectionPool(fn () => new HealthyConnection(), maxConnections: 1);
        $pool->acquire();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('exhausted');

        $pool->acquire();
    }

    public function testUnhealthyConnectionsAreNotHandedOut(): void
    {
        $pool = new ConnectionPool(fn () => new HealthyConnection(healthy: false), maxConnections: 1);

        $this->expectExceptionMessage('health check');

        $pool->acquire();
    }
}
