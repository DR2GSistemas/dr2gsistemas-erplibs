<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Auth;

use Dr2g\ErpLibs\Auth\AuthAudit;
use Dr2g\ErpLibs\Log\Logger;
use PHPUnit\Framework\TestCase;

/**
 * El logger de la librería escribe en stderr, así que para el test se usa una
 * subclase que captura las llamadas.
 */
final class CapturingLogger extends Logger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $entries = [];

    public function log(string $level, string $message, array $context = []): void
    {
        $this->entries[] = ['level' => $level, 'message' => $message, 'context' => $context];
    }
}

final class AuthAuditTest extends TestCase
{
    public function testRecordsLoginEvents(): void
    {
        $logger = new CapturingLogger();
        $audit = new AuthAudit($logger);

        $audit->login('ana@example.com', '1.1.1.1');
        $audit->loginFailed('ana@example.com', '1.1.1.1');
        $audit->logout('ana@example.com', '1.1.1.1');
        $audit->passwordChanged('ana@example.com', '1.1.1.1');

        self::assertSame(
            ['auth.login', 'auth.login_failed', 'auth.logout', 'auth.password_changed'],
            array_column($logger->entries, 'message')
        );
    }

    public function testIncludesEmailAndIp(): void
    {
        $logger = new CapturingLogger();

        (new AuthAudit($logger))->login('ana@example.com', '1.1.1.1');

        self::assertSame('ana@example.com', $logger->entries[0]['context']['email']);
        self::assertSame('1.1.1.1', $logger->entries[0]['context']['ip']);
    }

    public function testThrottledRecordsTheRetryWindow(): void
    {
        $logger = new CapturingLogger();

        (new AuthAudit($logger))->throttled('ana@example.com', 300, '1.1.1.1');

        self::assertSame('auth.login_throttled', $logger->entries[0]['message']);
        self::assertSame(300, $logger->entries[0]['context']['retry_after']);
    }

    public function testWithoutLoggerNothingHappens(): void
    {
        // Sin logger no debe fallar ni escribir.
        (new AuthAudit(null))->login('ana@example.com');

        self::assertTrue(true);
    }

    public function testNeverLogsSecrets(): void
    {
        $logger = new CapturingLogger();

        (new AuthAudit($logger))->login('ana@example.com', '1.1.1.1');

        $payload = json_encode($logger->entries[0]);

        self::assertStringNotContainsStringIgnoringCase('password', (string) $payload);
        self::assertStringNotContainsString('$2y$', (string) $payload);
    }
}
