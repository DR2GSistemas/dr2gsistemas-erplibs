<?php

namespace Dr2g\ErpLibs\Tests\Unit\CLI;

use Dr2g\ErpLibs\CLI\Commands\MigrateCommand;
use Dr2g\ErpLibs\CLI\Scaffold\AppScaffold;
use PHPUnit\Framework\TestCase;

final class MigrateCommandTest extends TestCase
{
    private string $project;

    private string $originalDirectory;

    /** @var array<string, string|false> */
    private array $environment = [];

    protected function setUp(): void
    {
        $this->originalDirectory = (string) getcwd();
        $this->project = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'erplibs-migrate-' . bin2hex(random_bytes(8));

        foreach (['DB_CONNECTION', 'DB_DATABASE', 'DB_DSN', 'APP_DATABASE'] as $name) {
            $this->environment[$name] = getenv($name);
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }

        mkdir($this->project . '/src/config', 0755, true);
        mkdir($this->project . '/migrations', 0755, true);
        file_put_contents(
            $this->project . '/.env',
            "DB_CONNECTION=sqlite\nDB_DATABASE=storage/custom.sqlite\n"
        );
        file_put_contents(
            $this->project . '/src/config/database.php',
            AppScaffold::configDatabase()
        );
        file_put_contents(
            $this->project . '/src/config/app.php',
            AppScaffold::configApp($this->project . '/src/views')
        );

        chdir($this->project);
    }

    protected function tearDown(): void
    {
        chdir($this->originalDirectory);

        foreach ($this->environment as $name => $value) {
            if ($value === false) {
                putenv($name);
                unset($_ENV[$name], $_SERVER[$name]);

                continue;
            }

            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->project, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($this->project);
    }

    public function testMigrateUsesTheProjectEnvDatabaseAndAppConfigMatchesIt(): void
    {
        ob_start();
        try {
            (new MigrateCommand())->handle([]);
        } finally {
            ob_end_clean();
        }

        $appConfig = require $this->project . '/src/config/app.php';
        self::assertSame(
            'sqlite:' . $this->project . '/storage/custom.sqlite',
            $appConfig['database']
        );
        self::assertFileExists($this->project . '/storage/custom.sqlite');
        self::assertFileDoesNotExist($this->project . '/storage/app.sqlite');
    }
}
