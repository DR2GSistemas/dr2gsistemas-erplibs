<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\CLI;

use Dr2g\ErpLibs\CLI\Scaffold\AppGenerator;
use PHPUnit\Framework\TestCase;

/**
 * El scaffold (web y API) debe integrar el sistema de caché:
 * variables en .env.template, trait en el controller y CacheManager en el kernel.
 */
final class CacheScaffoldTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/dr2g_cache_scaffold_' . bin2hex(random_bytes(4));
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

    public function testEnvTemplateDocumentsCacheVariables(): void
    {
        (new AppGenerator($this->base))->generate(null, 'proyect001', ['crud' => 'todo']);

        $env = (string) file_get_contents($this->base . '/.env.template');

        foreach (['CACHE_DRIVER', 'CACHE_PREFIX', 'CACHE_TTL', 'CACHE_PATH', 'REDIS_DSN', 'REDIS_PASSWORD', 'REDIS_DB', 'API_CACHE_TTL'] as $key) {
            self::assertStringContainsString($key . '=', $env, "falta {$key} en .env.template");
        }
    }

    public function testWebControllerUsesTheCacheTrait(): void
    {
        (new AppGenerator($this->base))->generate(null, 'proyect001', ['crud' => 'todo']);

        $controller = (string) file_get_contents($this->base . '/app/Controllers/TodoController.php');

        self::assertStringContainsString('use Dr2g\\ErpLibs\\Cache\\CachesResponses;', $controller);
        self::assertStringContainsString('use CachesResponses;', $controller);
        self::assertStringContainsString("cached('all'", $controller);
        self::assertStringContainsString("forgetCache(['todos'])", $controller);
    }

    public function testApiKernelExposesTheCacheManager(): void
    {
        (new AppGenerator($this->base))->generate(null, 'proyect001', ['crud' => 'todo', 'api' => true]);

        $kernel = (string) file_get_contents($this->base . '/app/Http/ApiKernel.php');

        self::assertStringContainsString('use Dr2g\\ErpLibs\\Cache\\CacheManager;', $kernel);
        self::assertStringContainsString('public function cache(): CacheManager', $kernel);
        self::assertStringContainsString("'cache' => \$this->cache()", $kernel);
    }

    public function testApiKernelErrorHandlerCanAccessInstanceState(): void
    {
        (new AppGenerator($this->base))->generate(null, 'proyect001', ['api' => true]);

        $kernel = (string) file_get_contents($this->base . '/app/Http/ApiKernel.php');

        self::assertStringContainsString('setErrorHandler(function (', $kernel);
        self::assertStringNotContainsString('setErrorHandler(static function', $kernel);
    }

    public function testApiResourceControllerReliesOnApiCrudCache(): void
    {
        (new AppGenerator($this->base))->generate(null, 'proyect001', ['crud' => 'todo', 'api' => true]);

        self::assertFileExists($this->base . '/app/Controllers/Api/TodoController.php');

        $controller = (string) file_get_contents($this->base . '/app/Controllers/Api/TodoController.php');
        self::assertStringContainsString('extends ApiCrud', $controller);
        self::assertFileExists($this->base . '/docs/api.md');
    }
}
