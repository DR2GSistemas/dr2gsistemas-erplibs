<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\CLI;

use Dr2g\ErpLibs\CLI\Scaffold\AppGenerator;
use Dr2g\ErpLibs\CLI\Scaffold\MakeScaffold;
use Dr2g\ErpLibs\CLI\Scaffold\ProjectContext;
use PHPUnit\Framework\TestCase;

/**
 * Los comandos `make:*` trabajan sobre un proyecto generado: namespace y carpeta
 * salen de su composer.json.
 */
final class MakeCommandsTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/dr2g_make_' . bin2hex(random_bytes(4));
        (new AppGenerator($this->base))->generate('Acme', 'Acme');
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

    private function run_(string $command, string $arguments = ''): string
    {
        $script = implode(' ', [
                escapeshellarg(PHP_BINARY),
                escapeshellarg(dirname(__DIR__, 3) . '/bin/erplibs'),
                $command,
                $arguments,
                '--path=' . escapeshellarg($this->base),
            ]);

        exec($script . ' 2>&1', $output, $exitCode);

        self::assertSame(0, $exitCode, implode("\n", $output));

        return implode("\n", $output);
    }

    private function read(string $relative): string
    {
        return (string) file_get_contents($this->base . '/' . $relative);
    }

    public function testProjectContextReadsTheRootNamespace(): void
    {
        $project = ProjectContext::detect($this->base);

        self::assertSame('Acme', $project->namespace);
        self::assertSame(str_replace('\\', '/', $this->base) . '/src', str_replace('\\', '/', $project->source));
    }

    public function testProjectContextFailsOutsideAGeneratedProject(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No se encontró un proyecto generado');

        ProjectContext::detect(sys_get_temp_dir() . '/dr2g_nada_' . bin2hex(random_bytes(4)));
    }

    public function testMakeModelDerivesTheTableName(): void
    {
        $this->run_('make:model', 'Product');
        $this->run_('make:model', 'Category');

        self::assertStringContainsString("#[Table(name: 'products')]", $this->read('src/Models/Product.php'));
        self::assertStringContainsString('namespace Acme\\Models;', $this->read('src/Models/Product.php'));
        // Plural irregular: -y -> -ies.
        self::assertStringContainsString("#[Table(name: 'categories')]", $this->read('src/Models/Category.php'));
    }

    /**
     * El ORM rellena created_at/updated_at al guardar y delete() marca
     * deleted_at, pero sólo si el modelo declara esas columnas.
     */
    public function testMakeModelIncludesTimestampsAndSoftDeletes(): void
    {
        $this->run_('make:model', 'Product');

        $model = $this->read('src/Models/Product.php');

        foreach (['created_at', 'updated_at', 'deleted_at'] as $column) {
            self::assertStringContainsString("public ?string \${$column} = null;", $model, "falta {$column}");
        }

        self::assertStringContainsString("#[Column(type: 'DATETIME', nullable: true)]", $model);
    }

    public function testMakeModelAcceptsACustomTable(): void
    {
        $this->run_('make:model', 'Invoice --table=facturas');

        self::assertStringContainsString("#[Table(name: 'facturas')]", $this->read('src/Models/Invoice.php'));
    }

    public function testMakeControllerRendersTheViewOfTheRequestedResource(): void
    {
        $this->run_('make:controller', 'products');

        $controller = $this->read('src/Controllers/ProductsController.php');

        self::assertStringContainsString('namespace Acme\\Controllers;', $controller);
        self::assertStringContainsString('public function index(Request $request)', $controller);
        // La vista se deriva del nombre pedido, no del nombre de la clase.
        self::assertStringContainsString("layout('products.index'", $controller);
    }

    public function testMakeResourceControllerDeclaresEveryAction(): void
    {
        $this->run_('make:controller', 'Invoice --resource');

        $controller = $this->read('src/Controllers/InvoiceController.php');

        foreach (['index', 'show', 'store', 'update', 'destroy'] as $action) {
            self::assertStringContainsString($action . '(Request', $controller);
        }
    }

    public function testMakeApiControllerExtendsApiCrud(): void
    {
        $this->run_('make:controller', 'Product --api');

        $controller = $this->read('src/Controllers/Api/ProductController.php');

        self::assertStringContainsString('namespace Acme\\Controllers\\Api;', $controller);
        self::assertStringContainsString('extends ApiCrud', $controller);
        self::assertStringContainsString('use Acme\\Models\\Product;', $controller);
    }

    public function testMakeViewUsesSlashNotDotInTheFileName(): void
    {
        $this->run_('make:view', 'products.show');

        // Renderer resuelve `products.show` como views/products/show.php.
        self::assertFileExists($this->base . '/src/views/products/show.php');
        self::assertFileDoesNotExist($this->base . '/src/views/products.show.php');
        self::assertStringContainsString('src/views/products/show.php', $this->read('src/views/products/show.php'));
    }

    public function testMakeRouteRegistersTheRoute(): void
    {
        $this->run_('make:route', '/products --controller=ProductController@index');

        $route = $this->read('src/routes/products.php');

        self::assertStringContainsString("\$kernel->router()->get('/products'", $route);
        self::assertStringContainsString('Acme\\Controllers\\ProductController@index', $route);
    }

    public function testMakeMiddlewareAndRequestUseTheProjectNamespace(): void
    {
        $this->run_('make:middleware', 'Auth');
        $this->run_('make:request', 'StoreProduct');

        self::assertStringContainsString('namespace Acme\\Http\\Middleware;', $this->read('src/Http/Middleware/Auth.php'));
        self::assertStringContainsString('__invoke(Request $request, callable $next)', $this->read('src/Http/Middleware/Auth.php'));

        // El sufijo Request no se duplica.
        $request = $this->read('src/Http/Requests/StoreProductRequest.php');
        self::assertStringContainsString('namespace Acme\\Http\\Requests;', $request);
        self::assertStringContainsString('class StoreProductRequest', $request);
    }

    public function testMakeSubviewWritesIntoPartials(): void
    {
        $this->run_('make:subview', 'badge');

        self::assertFileExists($this->base . '/src/views/partials/badge.php');
        self::assertStringContainsString("<?= \$subview('partials.badge'", $this->read('src/views/partials/badge.php'));
        self::assertStringContainsString('src/views/partials/badge.php', $this->read('src/views/partials/badge.php'));
    }

    public function testMakeSubviewAcceptsThePartialsPrefixAndNesting(): void
    {
        $this->run_('make:subview', 'partials.card');
        $this->run_('make:subview', 'users/row');

        self::assertFileExists($this->base . '/src/views/partials/card.php');
        self::assertFileExists($this->base . '/src/views/partials/users/row.php');
    }

    public function testMakeRepositoryExtendsTheLibraryBase(): void
    {
        $this->run_('make:model', 'Product');
        $this->run_('make:repository', 'Product');

        $repository = $this->read('src/Repositories/ProductRepository.php');

        self::assertStringContainsString('namespace Acme\\Repositories;', $repository);
        self::assertStringContainsString('use Dr2g\\ErpLibs\\Repositories\\Repository;', $repository);
        self::assertStringContainsString('use Acme\\Models\\Product;', $repository);
        self::assertStringContainsString('final class ProductRepository extends Repository', $repository);
        self::assertStringContainsString('return Product::class;', $repository);
    }

    public function testMakeRepositoryAcceptsACustomModelAndKeepsTheSuffix(): void
    {
        $this->run_('make:repository', 'UserRepository --model=Category');

        $repository = $this->read('src/Repositories/UserRepository.php');

        self::assertStringContainsString('use Acme\\Models\\Category;', $repository);
        // El sufijo Repository no se duplica.
        self::assertStringContainsString('class UserRepository extends Repository', $repository);
    }

    public function testMakeTestPlacesFeatureTestsInTheirFolder(): void
    {
        $this->run_('make:test', 'ProductCrud --feature');
        $this->run_('make:test', 'UnitThing');

        self::assertStringContainsString('final class ProductCrudTest extends TestCase', $this->read('src/tests/Feature/ProductCrudTest.php'));
        self::assertFileExists($this->base . '/src/tests/Unit/UnitThingTest.php');
    }

    public function testItRefusesToOverwriteWithoutForce(): void
    {
        $this->run_('make:model', 'Product');

        $script = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 3) . '/bin/erplibs')
            . ' make:model Product --path=' . escapeshellarg($this->base) . ' 2>&1';

        exec($script, $output, $exitCode);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Ya existe src/Models/Product.php', implode("\n", $output));

        // Con --force sí sobrescribe.
        $this->run_('make:model', 'Product --force');
    }

    public function testGeneratedClassesAreSyntacticallyValid(): void
    {
        $this->run_('make:model', 'Product');
        $this->run_('make:controller', 'Product');
        $this->run_('make:controller', 'Invoice --resource');
        $this->run_('make:controller', 'Product --api');
        $this->run_('make:middleware', 'Auth');
        $this->run_('make:request', 'StoreProduct');
        $this->run_('make:test', 'ProductCrud');

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->base . '/src', \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $output, $exitCode);
                self::assertSame(0, $exitCode, $file->getFilename() . ': ' . implode(' ', $output));
            }
        }
    }

    public function testNamingHelpers(): void
    {
        self::assertSame('ProductoItems', MakeScaffold::studly('producto-items'));
        self::assertSame('product_item', MakeScaffold::snake('ProductItem'));
        self::assertSame('users', MakeScaffold::table('User'));
        self::assertSame('categories', MakeScaffold::table('Category'));
        self::assertSame('addresses', MakeScaffold::table('Address'));
        self::assertSame('show', MakeScaffold::action('Users/Show'));
    }
}
