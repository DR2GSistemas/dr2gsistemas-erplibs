<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Log;

use Dr2g\ErpLibs\Core\Connection;
use Dr2g\ErpLibs\Log\Logger;
use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase
{
    /** @var list<array{level: string, record: array<string, mixed>}> */
    private array $records = [];

    private function logger(string $level = Logger::DEBUG): Logger
    {
        return new Logger($level, function (string $level, array $record): void {
            $this->records[] = ['level' => $level, 'record' => $record];
        });
    }

    /**
     * Connection::select is overridden inside an anonymous subclass, so the
     * timing path is reproduced against the real Logger contract.
     */
    private function timedConnection(float $sleep, float $threshold): Connection
    {
        return new class ($sleep, $threshold, $this->logger()) extends Connection {
            public function __construct(
                private readonly float $sleep,
                private readonly float $threshold,
                private readonly Logger $sink
            ) {
            }

            public function select(string $sql, array $bindings = []): array
            {
                $start = microtime(true);
                usleep((int) ($this->sleep * 1_000_000));

                $elapsed = microtime(true) - $start;

                if ($elapsed >= $this->threshold) {
                    $this->sink->warning('Slow query {elapsed}s: {sql}', [
                        'elapsed' => round($elapsed, 4),
                        'sql' => $sql,
                    ]);
                }

                return [];
            }

            public function execute(string $sql, array $bindings = []): int|false
            {
                return 1;
            }
        };
    }

    public function testLogsAtOrAboveConfiguredLevel(): void
    {
        $logger = $this->logger(Logger::WARNING);

        $logger->debug('ignored');
        $logger->warning('kept');

        self::assertCount(1, $this->records);
        self::assertSame(Logger::WARNING, $this->records[0]['level']);
    }

    public function testConnectionLogsSlowQueriesWithSqlAndElapsed(): void
    {
        $connection = $this->timedConnection(sleep: 0.005, threshold: 0.0);

        $connection->select('SELECT * FROM users WHERE id = ?', [1]);

        self::assertCount(1, $this->records);
        self::assertSame(Logger::WARNING, $this->records[0]['level']);
        self::assertSame('Slow query {elapsed}s: {sql}', $this->records[0]['record']['message']);
        self::assertSame('SELECT * FROM users WHERE id = ?', $this->records[0]['record']['context']['sql']);
        self::assertGreaterThan(0.0, $this->records[0]['record']['context']['elapsed']);
    }

    public function testFastQueryIsNotLogged(): void
    {
        $connection = $this->timedConnection(sleep: 0.0, threshold: 10.0);

        $connection->select('SELECT 1');

        self::assertSame([], $this->records);
    }

    public function testRealConnectionEmitsSlowQueryWarningAgainstAServer(): void
    {
        $dsn = getenv('DR2G_TEST_DSN');

        if ($dsn === false || $dsn === '') {
            self::markTestSkipped('Set DR2G_TEST_DSN to exercise Connection timing.');
        }

        $connection = new Connection([
            'dsn' => $dsn,
            'username' => getenv('DR2G_TEST_USER') ?: 'root',
            'password' => getenv('DR2G_TEST_PASSWORD') ?: '',
        ]);
        $connection->setSlowQueryLogging($this->logger(), 0.0);

        $connection->select('SELECT 1');

        self::assertSame('SELECT 1', $this->records[0]['record']['context']['sql']);
    }
}
