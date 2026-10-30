<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\CLI;

use Dr2g\ErpLibs\CLI\Scaffold\AppGenerator;
use Dr2g\ErpLibs\Config\DotEnv;
use PHPUnit\Framework\TestCase;

final class EnvScaffoldTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/dr2g_envapp_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->base)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->base);
    }

    public function testCreatesEnvFiles(): void
    {
        $created = (new AppGenerator($this->base))->generate(null, 'proyect001', ['crud' => 'todo']);

        self::assertContains('.env.template', $created);
        self::assertContains('.env', $created);
        self::assertContains('.env.local.template', $created);
        self::assertContains('.gitignore', $created);
    }

    public function testTemplateDocumentsAppDatabaseAndInitialData(): void
    {
        (new AppGenerator($this->base))->generate(null, 'proyect001', ['crud' => 'todo']);

        $template = (string) file_get_contents($this->base . '/.env.template');

        foreach ([
            'APP_NAME', 'APP_ENV', 'APP_DEBUG', 'APP_URL', 'APP_PORT', 'APP_KEY',
            'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
            'INSTALL_ADMIN_EMAIL', 'MAIL_FROM', 'LOG_LEVEL', 'SEED_TODOS',
        ] as $key) {
            self::assertStringContainsString($key . '=', $template, "falta {$key} en .env.template");
        }
    }

    public function testEnvHasGeneratedSecretsAndCleanValues(): void
    {
        (new AppGenerator($this->base))->generate(null, 'proyect001', ['crud' => 'todo']);

        foreach (['DR2G_ENV_APP_KEY', 'DR2G_ENV_ADMIN', 'DR2G_ENV_PORT', 'DR2G_ENV_DB', 'DR2G_ENV_NAME'] as $key) {
            putenv($key);
        }

        $env = DotEnv::templateDefaults($this->base . '/.env');

        self::assertSame(64, strlen($env['APP_KEY'] ?? ''), 'APP_KEY debe ser una clave de 32 bytes');
        self::assertNotSame('', $env['INSTALL_ADMIN_PASSWORD'] ?? '', 'debe generarse contraseña inicial');
        self::assertSame('8000', $env['APP_PORT'] ?? '');
        self::assertSame('sqlite', $env['DB_CONNECTION'] ?? '');
        self::assertSame('true', $env['SEED_TODOS'] ?? '');
        self::assertSame('proyect001', $env['APP_NAME'] ?? '');
    }

    public function testGitignoreExcludesEnvFiles(): void
    {
        (new AppGenerator($this->base))->generate('Acme', 'Acme');

        $lines = array_map('trim', explode("\n", (string) file_get_contents($this->base . '/.gitignore')));

        self::assertContains('.env', $lines);
        self::assertContains('.env.local', $lines);
        self::assertContains('.env.*.local', $lines);
    }

    public function testBootstrapLoadsDotEnv(): void
    {
        (new AppGenerator($this->base))->generate('Acme', 'Acme');

        $bootstrap = (string) file_get_contents($this->base . '/bootstrap.php');

        self::assertStringContainsString("DotEnv::load(__DIR__ . '/.env')", $bootstrap);
        self::assertStringContainsString("DotEnv::load(__DIR__ . '/.env.local')", $bootstrap);
    }

    public function testConfigReadsEnvironment(): void
    {
        (new AppGenerator($this->base))->generate('Acme', 'Acme');

        $app = (string) file_get_contents($this->base . '/src/config/app.php');
        $db = (string) file_get_contents($this->base . '/src/config/database.php');

        self::assertStringContainsString("getenv('APP_NAME')", $app);
        self::assertStringContainsString("getenv('APP_PORT')", $app);
        self::assertStringContainsString('DB_CONNECTION', $db);
        self::assertStringContainsString('sqlite', $db);
        self::assertStringContainsString('pgsql', $db);
    }

    public function testServerUsesAppPortFromEnv(): void
    {
        (new AppGenerator($this->base))->generate('Acme', 'Acme');

        $bin = (string) file_get_contents($this->base . '/bin/app');

        self::assertStringContainsString("getenv('APP_PORT')", $bin);
    }
}
