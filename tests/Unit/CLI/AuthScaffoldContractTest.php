<?php

namespace Dr2g\ErpLibs\Tests\Unit\CLI;

use Dr2g\ErpLibs\CLI\Contracts\AuthRoleRepository;
use Dr2g\ErpLibs\CLI\Contracts\AuthUserRepository;
use Dr2g\ErpLibs\CLI\Scaffold\AuthScaffold;
use PHPUnit\Framework\TestCase;

final class AuthScaffoldContractTest extends TestCase
{
    private string $directory;

    private \Closure $autoload;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'erplibs-auth-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0755, true);

        foreach (AuthScaffold::files('AuthScaffoldFixture') as $path => $content) {
            $file = $this->directory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
            $directory = dirname($file);

            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            file_put_contents($file, $content);
        }

        $root = $this->directory;
        $this->autoload = static function (string $class) use ($root): void {
            $prefix = 'AuthScaffoldFixture\\';

            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $file = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR
                . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix))) . '.php';

            if (is_file($file)) {
                require_once $file;
            }
        };
        spl_autoload_register($this->autoload);
    }

    protected function tearDown(): void
    {
        spl_autoload_unregister($this->autoload);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($this->directory);
    }

    public function testGeneratedRepositoriesImplementTheRoleAssignmentContracts(): void
    {
        self::assertTrue(is_subclass_of('AuthScaffoldFixture\\Repositories\\UserRepository', AuthUserRepository::class));
        self::assertTrue(is_subclass_of('AuthScaffoldFixture\\Repositories\\RoleRepository', AuthRoleRepository::class));
    }
}
