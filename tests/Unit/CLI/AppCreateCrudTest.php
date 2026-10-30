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
            'app/Models/Todo.php',
            'migrations/2026_01_01_000000_create_todos_table.php',
            'app/Controllers/TodoController.php',
            'app/Support/Database.php',
            'views/todo/index.php',
            'views/todo/form.php',
            'views/todo/show.php',
            'routes/todo.php',
            'tests/Feature/TodoCrudTest.php',
        ] as $expected) {
            self::assertContains($expected, $created, "falta {$expected}");
            self::assertFileExists($this->base . '/' . $expected);
        }
    }

    public function testNamespaceComesFromProjectName(): void
    {
        (new AppGenerator($this->base))->generate(null, 'proyect001', ['crud' => 'todo']);

        $model = (string) file_get_contents($this->base . '/app/Models/Todo.php');
        $routes = (string) file_get_contents($this->base . '/routes/todo.php');
        $web = (string) file_get_contents($this->base . '/routes/web.php');

        self::assertStringContainsString('namespace Proyect001\\App\\Models;', $model);
        self::assertStringContainsString('Proyect001\\App\\Controllers\\TodoController', $routes);
        self::assertStringContainsString("require __DIR__ . '/todo.php';", $web);
    }

    public function testCrudRoutesAreRegisteredInTheApp(): void
    {
        (new AppGenerator($this->base))->generate('Acme', 'Acme', ['crud' => 'todo']);

        $routes = (string) file_get_contents($this->base . '/routes/todo.php');

        foreach (['/todos', '/todos/new', '/todos/{id}', '/todos/{id}/edit', '/todos/{id}/toggle', '/todos/{id}/delete'] as $path) {
            self::assertStringContainsString($path, $routes, "falta la ruta {$path}");
        }
    }

    public function testWithoutCrudThereAreNoTodoFiles(): void
    {
        $created = (new AppGenerator($this->base))->generate('Acme', 'Acme');

        self::assertNotContains('app/Models/Todo.php', $created);
        self::assertNotContains('routes/todo.php', $created);
        self::assertFileExists($this->base . '/routes/web.php');
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
}
