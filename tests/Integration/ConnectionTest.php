<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Integration;

use Dr2g\ErpLibs\Core\Connection;
use Dr2g\ErpLibs\Core\DatabaseManager;
use PHPUnit\Framework\TestCase;

/**
 * Runs only when DR2G_TEST_DSN points to a disposable database, e.g.
 * DR2G_TEST_DSN="mysql:host=127.0.0.1;port=3306;dbname=azulerp_test".
 */
final class ConnectionTest extends TestCase
{
    private ?Connection $connection = null;

    protected function setUp(): void
    {
        $dsn = getenv('DR2G_TEST_DSN');

        if ($dsn === false || $dsn === '') {
            self::markTestSkipped('Set DR2G_TEST_DSN to run integration tests.');
        }

        $this->connection = new Connection([
            'dsn' => $dsn,
            'username' => getenv('DR2G_TEST_USER') ?: 'root',
            'password' => getenv('DR2G_TEST_PASSWORD') ?: '',
        ]);
    }

    public function testSelectReturnsRows(): void
    {
        self::assertSame([['n' => 1]], $this->connection->select('SELECT 1 AS n'));
    }

    public function testTransactionGuardToleratesImplicitCommit(): void
    {
        $this->connection->beginTransaction();
        self::assertTrue($this->connection->inTransaction());
        self::assertTrue($this->connection->commit());
        self::assertFalse($this->connection->commit());
        self::assertFalse($this->connection->rollBack());
    }

    public function testDatabaseManagerResolvesDefaultConnection(): void
    {
        $manager = new DatabaseManager([
            'default' => 'test',
            'connections' => [
                'test' => [
                    'dsn' => getenv('DR2G_TEST_DSN'),
                    'username' => getenv('DR2G_TEST_USER') ?: 'root',
                    'password' => getenv('DR2G_TEST_PASSWORD') ?: '',
                ],
            ],
        ]);

        self::assertInstanceOf(Connection::class, $manager->getConnection());
        self::assertSame($manager->getConnection(), $manager->getConnection());
    }
}
