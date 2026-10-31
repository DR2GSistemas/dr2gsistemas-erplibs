<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Core;

use Dr2g\ErpLibs\Core\Connection;
use Dr2g\ErpLibs\Testing\FakeConnection;
use PHPUnit\Framework\TestCase;

/**
 * `getDriver()` debe deducir el motor del DSN: la conexión SQLite del proyecto
 * sólo declara `dsn`, y sin esto las migraciones usaban DDL de MySQL.
 */
final class ConnectionDriverTest extends TestCase
{
    /**
     * FakeConnection no abre PDO: se usa para probar sólo la derivación.
     *
     * @param array<string, mixed> $config
     */
    private function withConfig(array $config): Connection
    {
        $connection = new FakeConnection();
        (new \ReflectionProperty(Connection::class, 'config'))->setValue($connection, $config);

        return $connection;
    }

    public function testUsesTheExplicitDriver(): void
    {
        self::assertSame('pgsql', $this->withConfig(['driver' => 'pgsql', 'dsn' => 'sqlite::memory:'])->getDriver());
    }

    public function testDerivesTheDriverFromTheDsn(): void
    {
        self::assertSame('sqlite', $this->withConfig(['dsn' => 'sqlite::memory:'])->getDriver());
        self::assertSame('mysql', $this->withConfig(['dsn' => 'mysql:host=127.0.0.1'])->getDriver());
        self::assertSame('pgsql', $this->withConfig(['dsn' => 'pgsql:host=127.0.0.1'])->getDriver());
    }

    public function testEmptyConfigMeansNoDriver(): void
    {
        self::assertSame('', $this->withConfig([])->getDriver());
        self::assertSame('', $this->withConfig(['driver' => ''])->getDriver());
    }
}
