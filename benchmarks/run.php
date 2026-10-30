<?php
// Benchmark reproducible sobre SQLite en memoria y un fichero temporal.
// Uso: php benchmarks/run.php [--json]
//
// Sustituye MySQL por SQLite: medimos el coste del ORM (hidratación, SQL
// builder, eventos), no el del driver. Los números de MySQL pueden ser otros.

require dirname(__DIR__) . '/vendor/autoload.php';

use Dr2g\ErpLibs\Core\Connection;
use Dr2g\ErpLibs\Models\Attributes\Column;
use Dr2g\ErpLibs\Models\Attributes\Table;
use Dr2g\ErpLibs\Models\Model;
use Dr2g\ErpLibs\Serialization\Paginator;

$file = sys_get_temp_dir() . '/dr2g_bench_' . getmypid() . '.sqlite';
@unlink($file);

$connection = new Connection(['dsn' => 'sqlite:' . $file]);
$connection->execute(
    'CREATE TABLE bench_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        score INTEGER NOT NULL DEFAULT 0
    )'
);

Model::setDbManager(new class ($connection) extends \Dr2g\ErpLibs\Core\DatabaseManager {
    public function __construct(private readonly Connection $shared)
    {
    }

    public function getConnection(string $name = 'default'): Connection
    {
        return $this->shared;
    }
});

#[Table(name: 'bench_users')]
class BenchUser extends Model
{
    #[Column(type: 'TEXT')]
    public string $name;

    #[Column(type: 'TEXT')]
    public string $email;

    #[Column(type: 'INTEGER')]
    public int $score = 0;
}

$rows = (int) (getenv('BENCH_ROWS') ?: 1000);

$time = static function (callable $operation): float {
    $start = hrtime(true);
    $operation();
    return (hrtime(true) - $start) / 1_000_000;
};

$results = [];

// Insert fila a fila (un INSERT por registro).
$results['insert one-by-one'] = $time(function () use ($rows) {
    for ($i = 1; $i <= $rows; $i++) {
        BenchUser::create(['name' => "User {$i}", 'email' => "user{$i}@example.com", 'score' => $i % 100]);
    }
});

// Insert por lotes en una transacción.
$results['insert in transaction'] = $time(function () use ($rows, $connection) {
    $start = $rows + 1;
    $connection->beginTransaction();
    for ($i = $start; $i < $start + $rows; $i++) {
        BenchUser::create(['name' => "User {$i}", 'email' => "user{$i}@example.com", 'score' => $i % 100]);
    }
    $connection->commit();
});

// Lectura completa + hidratación.
$results['all() hydration'] = $time(function () use ($rows) {
    $users = BenchUser::all();
    if (count($users) !== $rows * 2) {
        throw new \RuntimeException('Unexpected row count: ' . count($users));
    }
});

// Búsqueda por PK.
$results['find(1) x1000'] = $time(function () {
    for ($i = 0; $i < 1000; $i++) {
        BenchUser::find(1);
    }
});

// Filtro + orden + límite.
$results['query where/order/limit'] = $time(function () {
    for ($i = 0; $i < 100; $i++) {
        BenchUser::query()->where('score', '>', 50)->orderBy('name', 'DESC')->limit(10)->get();
    }
});

// Paginación por offset y por cursor.
$results['pagination offset x100'] = $time(function () {
    for ($i = 0; $i < 100; $i++) {
        Paginator::offset(BenchUser::query(), page: 2, perPage: 20);
    }
});

$results['pagination cursor x100'] = $time(function () {
    for ($i = 0; $i < 100; $i++) {
        Paginator::cursor(BenchUser::query(), 'id', after: 100, perPage: 20);
    }
});

// Serialización.
$results['serialize 1000 models'] = $time(function () {
    \Dr2g\ErpLibs\Serialization\Resource::collection(BenchUser::hydrateMany(BenchUser::query()->limit(1000)->get()));
});

$json = (bool) in_array('--json', $_SERVER['argv'], true);

$total = 0;
foreach ($results as $ms) {
    $total += $ms;
}

if ($json) {
    echo json_encode(['rows' => $rows, 'results_ms' => $results, 'total_ms' => round($total, 2)], JSON_PRETTY_PRINT), "\n";
} else {
    echo "Repositorio: SQLite en memoria ({$rows} filas por lote)\n\n";
    echo str_pad('Benchmark', 30), str_pad('Tiempo', 12), "Ops/s\n";
    echo str_repeat('-', 55), "\n";

    foreach ($results as $name => $ms) {
        printf("%-30s%-12s\n", $name, round($ms, 2) . ' ms');
    }

    printf("\nTotal: %.2f ms\n", $total);
}

@unlink($file);

exit(0);
