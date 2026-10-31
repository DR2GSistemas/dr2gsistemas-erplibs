<?php

namespace Dr2g\ErpLibs\Tests\Unit\CLI;

use Dr2g\ErpLibs\CLI\Commands\FeatureEnableCommand;
use Dr2g\ErpLibs\CLI\Scaffold\AppGenerator;
use PHPUnit\Framework\TestCase;

final class VueFeatureTest extends TestCase
{
    private string $project;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir() . '/dr2g_vue_' . bin2hex(random_bytes(6));
        ob_start();
        try {
            (new AppGenerator($this->project))->generate('VueFixture', 'VueFixture');
        } finally {
            ob_end_clean();
        }
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->project)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->project, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->project);
    }

    private function enable(string $feature): string
    {
        $output = '';
        ob_start();
        try {
            (new FeatureEnableCommand())->handle([$feature, '--path=' . $this->project]);
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }

    private function read(string $path): string
    {
        return (string) file_get_contents($this->project . '/' . $path);
    }

    /** @return array<string, mixed> */
    private function package(): array
    {
        return json_decode($this->read('package.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    public function testVueEnablesTypescriptCompositionApiAndViteInThePhpLayout(): void
    {
        $this->enable('vue');

        $package = $this->package();
        $layout = $this->read('src/views/layout.php');

        self::assertSame('^3.5.13', $package['dependencies']['vue']);
        self::assertSame('vue-tsc --noEmit && vite build', $package['scripts']['dr2g:build']);
        self::assertStringContainsString('<script setup lang="ts">', $this->read('resources/js/App.vue'));
        self::assertStringContainsString('import.meta.glob', $this->read('resources/js/app.ts'));
        self::assertStringContainsString('<main id="app">', $layout);
        self::assertStringContainsString('partials/vite-head.php', $layout);
        self::assertStringContainsString('partials/vite-scripts.php', $layout);
        self::assertFileExists($this->project . '/vite.config.ts');
        self::assertFileExists($this->project . '/tsconfig.json');
        self::assertStringContainsString('node_modules/', $this->read('.gitignore'));
        self::assertStringContainsString('public/build/', $this->read('.gitignore'));
    }

    public function testProductionLayoutReadsTheViteManifest(): void
    {
        $this->enable('vue');
        mkdir($this->project . '/public/build', 0755, true);
        file_put_contents(
            $this->project . '/public/build/manifest.json',
            json_encode([
                'resources/js/app.ts' => [
                    'file' => 'assets/app-abc.js',
                    'css' => ['assets/app-abc.css'],
                ],
            ], JSON_THROW_ON_ERROR)
        );

        $previousEnv = getenv('APP_ENV');
        putenv('APP_ENV=production');
        ob_start();
        try {
            require $this->project . '/src/views/partials/vite-head.php';
            require $this->project . '/src/views/partials/vite-scripts.php';
            $html = (string) ob_get_contents();
        } finally {
            ob_end_clean();

            if ($previousEnv === false) {
                putenv('APP_ENV');
            } else {
                putenv('APP_ENV=' . $previousEnv);
            }
        }

        self::assertStringContainsString('/build/assets/app-abc.css', $html);
        self::assertStringContainsString('/build/assets/app-abc.js', $html);
    }

    public function testMaterialThemeIsGeneratedAndCannotCoexistWithPrimeVue(): void
    {
        $this->enable('vue');
        $this->enable('material');

        self::assertFileExists($this->project . '/resources/css/material.css');
        self::assertStringContainsString('--md-primary:', $this->read('resources/css/material.css'));
        self::assertStringStartsWith("@import './material.css';", $this->read('resources/css/app.css'));

        $error = null;
        try {
            $this->enable('primevue');
        } catch (\RuntimeException $exception) {
            $error = $exception;
        }

        self::assertInstanceOf(\RuntimeException::class, $error);
        self::assertStringContainsString('son excluyentes', $error->getMessage());
    }

    public function testPrimeVueAddsItsPluginAndDependenciesAndCannotCoexistWithMaterial(): void
    {
        $this->enable('vue');
        $this->enable('primevue');

        $package = $this->package();

        self::assertSame('^4.2.5', $package['dependencies']['primevue']);
        self::assertArrayHasKey('@primeuix/themes', $package['dependencies']);
        self::assertStringContainsString('app.use(PrimeVue', $this->read('resources/js/plugins/primevue.ts'));

        $error = null;
        try {
            $this->enable('material');
        } catch (\RuntimeException $exception) {
            $error = $exception;
        }

        self::assertInstanceOf(\RuntimeException::class, $error);
        self::assertStringContainsString('son excluyentes', $error->getMessage());
    }

    public function testUiFeatureRequiresVueAndVueEnableIsIdempotent(): void
    {
        $error = null;
        try {
            $this->enable('material');
        } catch (\RuntimeException $exception) {
            $error = $exception;
        }

        self::assertInstanceOf(\RuntimeException::class, $error);
        self::assertStringContainsString('Habilita Vue primero', $error->getMessage());

        $this->enable('vue');
        self::assertStringContainsString('ya habilitado', $this->enable('vue'));
    }

    public function testPackageManifestIsMergedWithoutDiscardingExistingScripts(): void
    {
        file_put_contents($this->project . '/package.json', json_encode([
            'name' => 'existing-frontend',
            'scripts' => ['test' => 'echo test'],
            'dependencies' => ['dayjs' => '^1.11.0'],
        ], JSON_THROW_ON_ERROR));

        $this->enable('vue');
        $package = $this->package();

        self::assertSame('existing-frontend', $package['name']);
        self::assertSame('echo test', $package['scripts']['test']);
        self::assertSame('^1.11.0', $package['dependencies']['dayjs']);
        self::assertArrayHasKey('vue', $package['dependencies']);
    }

    public function testVueRejectsAnIncompatibleExistingDependencyBeforeWritingFiles(): void
    {
        file_put_contents($this->project . '/package.json', json_encode([
            'dependencies' => ['vue' => '^2.7.0'],
        ], JSON_THROW_ON_ERROR));

        $error = null;
        try {
            $this->enable('vue');
        } catch (\RuntimeException $exception) {
            $error = $exception;
        }

        self::assertInstanceOf(\RuntimeException::class, $error);
        self::assertStringContainsString('compatible con la versión 3', $error->getMessage());
        self::assertFileDoesNotExist($this->project . '/vite.config.ts');
    }

    public function testFeatureCommandIsRegisteredAndRejectsUnknownFeatures(): void
    {
        $bin = (string) file_get_contents(dirname(__DIR__, 3) . '/bin/erplibs');
        self::assertStringContainsString('new FeatureEnableCommand()', $bin);

        $error = null;
        try {
            $this->enable('svelte');
        } catch (\InvalidArgumentException $exception) {
            $error = $exception;
        }

        self::assertInstanceOf(\InvalidArgumentException::class, $error);
        self::assertStringContainsString('Feature desconocida', $error->getMessage());
    }
}
