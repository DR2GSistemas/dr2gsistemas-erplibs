<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\CLI;

use Dr2g\ErpLibs\CLI\Server\ServerConfig;
use PHPUnit\Framework\TestCase;

final class AppServeConfigTest extends TestCase
{
    private string $project;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir() . '/dr2g_serve_' . bin2hex(random_bytes(4));
        mkdir($this->project . '/public', 0755, true);
        file_put_contents($this->project . '/public/index.php', '<?php');
    }

    protected function tearDown(): void
    {
        @unlink($this->project . '/public/index.php');
        @rmdir($this->project . '/public');
        @rmdir($this->project);
    }

    /**
     * @param list<string> $arguments
     */
    private function config(array $arguments): ServerConfig
    {
        return ServerConfig::fromArguments($this->project, $arguments);
    }

    public function testDefaultsToPort8000AndLocalhost(): void
    {
        $config = $this->config([]);

        self::assertSame('127.0.0.1', $config->host);
        self::assertSame(8000, $config->port);
        self::assertSame($this->project . '/public', $config->docroot());
        self::assertSame($this->project . '/public/index.php', $config->routerFile());
        self::assertSame('http://127.0.0.1:8000', $config->url());
    }

    public function testPortOptionIsParsed(): void
    {
        self::assertSame(8002, $this->config(['--port=8002'])->port);
        self::assertSame('http://127.0.0.1:8002', $this->config(['--port=8002'])->url());
    }

    public function testSeparateValueFormIsAccepted(): void
    {
        self::assertSame(8002, $this->config(['--port', '8002'])->port);
        self::assertSame('0.0.0.0', $this->config(['--host', '0.0.0.0'])->host);
    }

    public function testMissingValueIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Falta el valor de --port');

        $this->config(['--port']);
    }

    public function testHostOptionIsParsedAndLocalhostIsShownForWildcard(): void
    {
        self::assertSame('0.0.0.0', $this->config(['--host=0.0.0.0'])->host);
        self::assertSame('http://localhost:8000', $this->config(['--host=0.0.0.0'])->url());
    }

    public function testProjectArgumentOverridesCwd(): void
    {
        $config = ServerConfig::fromArguments('/tmp', [$this->project, '--port=9000']);

        self::assertSame($this->project, $config->project);
        self::assertSame(9000, $config->port);
    }

    public function testCustomRouterIsResolvedRelativeToProject(): void
    {
        self::assertSame($this->project . '/public/index.php', $this->config(['--router=public/index.php'])->routerFile());
    }

    public function testCommandBuildsPhpServerInvocation(): void
    {
        $command = $this->config(['--port=8002'])->command();

        self::assertSame(PHP_BINARY, $command[0]);
        self::assertSame('-S', $command[1]);
        self::assertSame('127.0.0.1:8002', $command[2]);
        self::assertSame('-t', $command[3]);
        self::assertSame($this->project . '/public', $command[4]);
        self::assertSame($this->project . '/public/index.php', $command[5]);
    }

    public function testInvalidPortIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Puerto inválido');

        $this->config(['--port=0']);
    }

    public function testPortOverRangeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->config(['--port=70000']);
    }

    public function testNonNumericPortFallsBackToZeroAndFails(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->config(['--port=abc']);
    }

    public function testUnknownOptionIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Opción desconocida');

        $this->config(['--daemon']);
    }

    public function testValidateFailsWithoutPublicIndex(): void
    {
        $config = new ServerConfig($this->project . '/no-existe');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('public/index.php');

        $config->validate();
    }

    public function testValidatePassesForAValidProject(): void
    {
        $this->config([])->validate();

        self::assertTrue(true);
    }

    public function testPortInUseDetectsAnOpenSocket(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        self::assertIsResource($server);

        $name = stream_socket_get_name($server, false);
        $port = (int) substr((string) $name, strrpos((string) $name, ':') + 1);

        try {
            self::assertTrue((new ServerConfig($this->project, '127.0.0.1', $port))->portIsInUse());
        } finally {
            fclose($server);
        }
    }

    public function testPortInUseReturnsFalseForAFreePort(): void
    {
        self::assertFalse((new ServerConfig($this->project, '127.0.0.1', 65000))->portIsInUse());
    }
}
