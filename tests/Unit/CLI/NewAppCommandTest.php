<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\CLI;

use Dr2g\ErpLibs\CLI\Scaffold\AppGenerator;
use Dr2g\ErpLibs\CLI\Scaffold\AppScaffold;
use PHPUnit\Framework\TestCase;

final class NewAppCommandTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/dr2g_app_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->base)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->base, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }

            rmdir($this->base);
        }
    }

    public function testGeneratesRouteControllerAndViews(): void
    {
        $created = (new AppGenerator($this->base))->generate('Acme', 'Acme ERP');

        self::assertContains('src/routes/web.php', $created);
        self::assertContains('src/Controllers/HomeController.php', $created);
        self::assertContains('src/Http/Kernel.php', $created);
        self::assertContains('src/views/layout.php', $created);
        self::assertContains('src/views/home/index.php', $created);
        self::assertContains('src/views/errors/404.php', $created);

        foreach ($created as $relative) {
            self::assertFileExists($this->base . '/' . $relative);

            if (str_ends_with($relative, '.php')) {
                self::assertSame('', (string) shell_exec('php -l ' . escapeshellarg($this->base . '/' . $relative) . ' 2>&1 | grep -v "No syntax errors"') === ''
                    ? '' : '', $relative . ' lint');
            }
        }
    }

    public function testGeneratedFilesUseTheNamespace(): void
    {
        (new AppGenerator($this->base))->generate('Acme\\Erp', 'Acme');

        $controller = (string) file_get_contents($this->base . '/src/Controllers/HomeController.php');
        $composer = (string) file_get_contents($this->base . '/composer.json');
        $routes = (string) file_get_contents($this->base . '/src/routes/web.php');

        self::assertStringContainsString('namespace Acme\\Erp\\Controllers;', $controller);
        self::assertStringContainsString('"Acme\\\\Erp\\\\": "src/"', $composer);
        self::assertStringContainsString('Acme\\Erp\\Controllers\\HomeController@index', $routes);
    }

    public function testRefusesNonEmptyDirectoryWithoutForce(): void
    {
        mkdir($this->base, 0755, true);
        file_put_contents($this->base . '/existing.txt', 'x');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no está vacío');

        (new AppGenerator($this->base))->generate('Acme');
    }

    public function testForceOverwritesExistingFiles(): void
    {
        mkdir($this->base, 0755, true);
        file_put_contents($this->base . '/existing.txt', 'x');

        $created = (new AppGenerator($this->base))->generate('Acme', null, ['force' => true]);

        self::assertNotEmpty($created);
        self::assertFileExists($this->base . '/existing.txt');
        self::assertFileExists($this->base . '/src/routes/web.php');
    }

    public function testDryRunCreatesNothing(): void
    {
        $created = (new AppGenerator($this->base))->generate('Acme', null, ['dryRun' => true]);

        self::assertNotEmpty($created);
        self::assertDirectoryDoesNotExist($this->base);
    }

    public function testInvalidNamespaceIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new AppGenerator($this->base))->generate('acme-erp');
    }

    public function testGeneratedComposerJsonIsValidJson(): void
    {
        (new AppGenerator($this->base))->generate('Acme\\Erp', 'Acme ERP');

        $composer = json_decode((string) file_get_contents($this->base . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('acme-erp/acme-erp', $composer['name']);
        self::assertSame('src/', $composer['autoload']['psr-4']['Acme\\Erp\\']);
    }

    /**
     * Sin la librería en disco, un repositorio `path` con url vacía es inválido
     * y Composer acaba instalando la versión publicada.
     */
    public function testComposerJsonOmitsThePathRepositoryWhenTheLibraryIsMissing(): void
    {
        (new AppGenerator($this->base))->generate('Acme', 'Acme');

        $composer = json_decode((string) file_get_contents($this->base . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayNotHasKey('repositories', $composer);
        self::assertSame('*', $composer['require']['dr2gsistemas/erplibs']);
    }

    public function testGeneratedPhpFilesAreSyntacticallyValid(): void
    {
        $created = (new AppGenerator($this->base))->generate('Acme');

        foreach ($created as $relative) {
            if (!str_ends_with($relative, '.php')) {
                continue;
            }

            $file = $this->base . '/' . $relative;
            exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $exitCode);

            self::assertSame(0, $exitCode, "{$relative}: " . implode(' ', $output));
        }
    }

    public function testPackageNameIsSlugged(): void
    {
        self::assertSame('acme-erp', AppScaffold::slug('Acme ERP'));
        self::assertSame('app', AppScaffold::slug('---'));
    }

    /**
     * Jerarquía: código y recursos en src/; sólo public/ y migrations/ fuera.
     */
    public function testCodeLivesUnderSrc(): void
    {
        $created = (new AppGenerator($this->base))->generate('Acme', 'Acme', ['crud' => 'todo']);

        foreach ($created as $relative) {
            if (str_starts_with($relative, 'src/') || str_starts_with($relative, 'public/') || str_starts_with($relative, 'migrations/')) {
                continue;
            }

            self::assertContains(
                $relative,
                ['composer.json', 'bootstrap.php', 'bin/app', '.env', '.env.template', '.env.local.template', '.gitignore', 'README.md'],
                "{$relative} debería vivir en src/"
            );
        }

        foreach (['src/Controllers/HomeController.php', 'src/routes/web.php', 'src/views/layout.php', 'src/config/app.php', 'src/tests/Unit/App/KernelTest.php'] as $expected) {
            self::assertContains($expected, $created);
        }

        self::assertContains('migrations/2026_01_01_000000_create_todos_table.php', $created);
    }

    /**
     * Los tests generados dependen de rutas relativas: si las vistas no cargan,
     * el kernel responde 500 con su propia vista de error.
     */
    public function testGeneratedKernelTestPointsToTheViews(): void
    {
        (new AppGenerator($this->base))->generate('Acme', 'Acme');

        $test = (string) file_get_contents($this->base . '/src/tests/Unit/App/KernelTest.php');

        self::assertStringContainsString("dirname(__DIR__, 4) . '/src/views'", $test);
    }

    public function testApiModeSkipsTheWebKernelTest(): void
    {
        $created = (new AppGenerator($this->base))->generate('Acme', 'Acme', ['api' => true]);

        self::assertNotContains('src/tests/Unit/App/KernelTest.php', $created);
        self::assertContains('src/tests/Feature/ApiHealthTest.php', $created);
        self::assertContains('src/routes/api.php', $created);
    }
}
