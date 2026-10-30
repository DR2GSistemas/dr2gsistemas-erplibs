<?php

/*
 * Ejemplo de routing: rutas RESTful sobre el ORM, sin servidor web.
 * Uso: php examples/routes.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Dr2g\ErpLibs\Core\Connection;
use Dr2g\ErpLibs\Core\DatabaseManager;
use Dr2g\ErpLibs\Models\Attributes\Column;
use Dr2g\ErpLibs\Models\Attributes\Table;
use Dr2g\ErpLibs\Models\Model;
use Dr2g\ErpLibs\Routing\Controller;
use Dr2g\ErpLibs\Routing\Request;
use Dr2g\ErpLibs\Routing\Response;
use Dr2g\ErpLibs\Routing\Router;

#[Table(name: 'products')]
class Product extends Model
{
    #[Column(type: 'VARCHAR(120)', validation: ['required', 'min:2'])]
    public string $name;

    #[Column(type: 'DECIMAL(10,2)')]
    public float $price = 0.0;
}

$file = sys_get_temp_dir() . '/dr2g_routes_' . getmypid() . '.sqlite';
@unlink($file);

$connection = new Connection(['dsn' => 'sqlite:' . $file]);
$connection->execute(
    'CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(120) NOT NULL, price NUMERIC NOT NULL DEFAULT 0)'
);
Model::setDbManager(new DatabaseManager([
    'default' => 'sqlite',
    'connections' => ['sqlite' => ['dsn' => 'sqlite:' . $file]],
]));

class ProductController extends Controller
{
    public function index(Request $request): array
    {
        return ['data' => array_map(static fn (Product $p) => $p->toArray(), Product::all())];
    }

    public function show(Request $request): mixed
    {
        $product = Product::find($request->param('id'));

        if ($product === null) {
            throw new \Dr2g\ErpLibs\Routing\Exception\NotFoundException('Product not found.');
        }

        return $product->toArray();
    }

    public function store(Request $request): Response
    {
        $product = Product::create($request->json());

        return Response::json($product->toArray(), 201);
    }

    public function update(Request $request): mixed
    {
        $product = Product::find($request->param('id'));

        if ($product === null) {
            throw new \Dr2g\ErpLibs\Routing\Exception\NotFoundException('Product not found.');
        }

        $product->fill($request->json())->save();

        return $product->toArray();
    }

    public function destroy(Request $request): mixed
    {
        Product::find($request->param('id'))?->delete();

        return null;
    }
}

$router = new Router();

// Middleware global: loguea método y path y añade cabecera de trazabilidad.
// El middleware debe devolver una Response: si devuelve datos, se normaliza aquí.
$router->use(static function (Request $request, callable $next) {
    $result = $next($request);
    $response = $result instanceof Response ? $result : Response::json((array) $result);

    return $response->withHeader('X-Route', $request->method . ' ' . $request->path);
});

$router->group('/api', static function ($routes): void {
    $routes->resource('/products', ProductController::class);
    $routes->get('/health', static fn () => ['status' => 'ok']);
});

$cases = [
    ['POST', '/api/products', '{"name":"Teclado","price":25.5}'],
    ['POST', '/api/products', '{"name":"Mouse","price":10}'],
    ['GET', '/api/products', ''],
    ['GET', '/api/products/1', ''],
    ['PUT', '/api/products/1', '{"price":30}'],
    ['DELETE', '/api/products/2', ''],
    ['GET', '/api/products/999', ''],
    ['PATCH', '/api/products', ''],
    ['POST', '/api/products', '{"name":"X"}'],
    ['GET', '/api/health', ''],
];

foreach ($cases as [$method, $path, $body]) {
    $response = $router->dispatch(Request::create($method, $path, body: $body));
    printf(
        "%-6s %-22s %d %s\n",
        $method,
        $path,
        $response->status,
        $response->body
    );
}

@unlink($file);
exit(0);
