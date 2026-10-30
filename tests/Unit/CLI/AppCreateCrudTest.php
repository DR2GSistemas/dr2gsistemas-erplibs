<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\CLI;

use Dr2g\ErpLibs\CLI\Scaffold\AppGenerator;
use PHPUnit\Framework\TestCase;

final class AppCreateCrudTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/dr2g_crud_' . bin2hex(random_bytes(4));
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

    public function testCreatesTodoCrudElements(): void
    {
        $created = (new AppGenerator($this->base))->generate(null, 'proyect001', ['crud' => 'todo']);

        foreach ([
            'src/Models/Todo.php',
            'migrations/2026_01_01_000000_create_todos_table.php',
            'src/Controllers/TodoController.php',
            'src/Support/Database.php',
            'src/views/todo/index.php',
            'src/views/todo/form.php',
            'src/views/todo/show.php',
            'src/routes/todo.php',
            'src/tests/Feature/TodoCrudTest.php',
        ] as $expected) {
            self::assertContains($expected, $created, "falta {$expected}");
            self::assertFileExists($this->base . '/' . $expected);
        }
    }

    public function testNamespaceComesFromProjectName(): void
    {
        (new AppGenerator($this->base))->generate(null, 'proyect001', ['crud' => 'todo']);

        $model = (string) file_get_contents($this->base . '/src/Models/Todo.php');
        $routes = (string) file_get_contents($this->base . '/src/routes/todo.php');
        $web = (string) file_get_contents($this->base . '/src/routes/web.php');

        self::assertStringContainsString('namespace Proyect001\\Models;', $model);
        self::assertStringContainsString('Proyect001\\Controllers\\TodoController', $routes);
        self::assertStringContainsString("require __DIR__ . '/todo.php';", $web);
    }

    public function testCrudRoutesAreRegisteredInTheApp(): void
    {
        (new AppGenerator($this->base))->generate('Acme', 'Acme', ['crud' => 'todo']);

        $routes = (string) file_get_contents($this->base . '/src/routes/todo.php');

        foreach (['/todos', '/todos/new', '/todos/{id}', '/todos/{id}/edit', '/todos/{id}/toggle', '/todos/{id}/delete'] as $path) {
            self::assertStringContainsString($path, $routes, "falta la ruta {$path}");
        }
    }

    public function testWithoutCrudThereAreNoTodoFiles(): void
    {
        $created = (new AppGenerator($this->base))->generate('Acme', 'Acme');

        self::assertNotContains('src/Models/Todo.php', $created);
        self::assertNotContains('src/routes/todo.php', $created);
        self::assertFileExists($this->base . '/src/routes/web.php');
    }

    public function testUnknownCrudIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CRUD no soportado');

        (new AppGenerator($this->base))->generate('Acme', 'Acme', ['crud' => 'invoice']);
    }

    public function testDefaultNamespaceFromName(): void
    {
        self::assertSame('Proyect001', AppGenerator::defaultNamespace('proyect001'));
        self::assertSame('Mi\\Erp', AppGenerator::defaultNamespace('mi-erp'));
        self::assertSame('App', AppGenerator::defaultNamespace('---'));
    }

    public function testGeneratedCrudPhpFilesAreSyntacticallyValid(): void
    {
        $created = (new AppGenerator($this->base))->generate(null, 'proyect001', ['crud' => 'todo']);

        foreach ($created as $relative) {
            if (!str_ends_with($relative, '.php')) {
                continue;
            }

            $output = [];
            exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($this->base . '/' . $relative) . ' 2>&1', $output, $exitCode);

            self::assertSame(0, $exitCode, "{$relative}: " . implode(' ', $output));
        }
    }

    /**
     * Los tests que genera el scaffold deben pasar tal cual: cubren las rutas
     * relativas de vistas, rutas y cabeceras JSON que los smoke tests no ven.
     *
     * @dataProvider moduloProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('moduloProvider')]
    public function testGeneratedTestsPass(bool $api): void
    {
        (new AppGenerator($this->base))->generate('Acme', 'Acme', ['crud' => 'todo', 'api' => $api]);

        $phpunit = dirname(__DIR__, 3) . '/vendor/bin/phpunit';

        // Autoloader mínimo del proyecto generado (sin composer install).
        $bootstrap = $this->base . '/../generated_autoload.php';
        file_put_contents($bootstrap, sprintf(
            "<?php\nspl_autoload_register(static function (string \$c): void {\n    \$p = %s;\n    if (str_starts_with(\$c, \$p) && is_file(\$f = %s . '/' . str_replace('\\\\\\\\', '/', substr(\$c, strlen(\$p))) . '.php')) { require \$f; }\n});\nrequire %s;\n",
            var_export('Acme\\', true),
            var_export($this->base . '/src', true),
            var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true)
        ));

        $command = implode(' ', [
            escapeshellarg(PHP_BINARY),
            escapeshellarg($phpunit),
            '--no-configuration',
            '--bootstrap ' . escapeshellarg($bootstrap),
            escapeshellarg($this->base . '/src/tests'),
        ]);

        exec($command . ' 2>&1', $output, $exitCode);

        self::assertSame(0, $exitCode, implode("\n", $output));
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function moduloProvider(): array
    {
        return [
            'web' => [false],
            'api' => [true],
        ];
    }
}
