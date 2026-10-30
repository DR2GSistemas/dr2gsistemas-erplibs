<?php
// Demostración ejecutable del ORM. Usa SQLite temporal para no depender de MySQL.
// Uso: php examples/run.php

require dirname(__DIR__) . '/vendor/autoload.php';

use Dr2g\ErpLibs\Cache\ArrayCache;
use Dr2g\ErpLibs\Core\Connection;
use Dr2g\ErpLibs\Core\DatabaseManager;
use Dr2g\ErpLibs\Events\Dispatcher;
use Dr2g\ErpLibs\Models\Attributes\Column;
use Dr2g\ErpLibs\Models\Attributes\Hidden;
use Dr2g\ErpLibs\Models\Attributes\Table;
use Dr2g\ErpLibs\Models\Model;
use Dr2g\ErpLibs\Serialization\Paginator;
use Dr2g\ErpLibs\Serialization\Resource;

#[Table(name: 'users')]
class User extends Model
{
    #[Column(type: 'VARCHAR(255)', validation: ['required', 'min:2'])]
    public string $name;

    #[Column(type: 'VARCHAR(255)', validation: ['required', 'email'])]
    public string $email;

    #[Hidden]
    #[Column(type: 'VARCHAR(255)')]
    public string $password_hash;
}

$file = sys_get_temp_dir() . '/azulerp_example_' . getmypid() . '.sqlite';
@unlink($file);

$connection = new Connection(['dsn' => 'sqlite:' . $file]);
$connection->execute(
    'CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        password_hash VARCHAR(255) NOT NULL
    )'
);
// lastInsertId() funciona igual con el driver sqlite de PDO.
Model::setDbManager(new DatabaseManager([
    'default' => 'sqlite',
    'connections' => ['sqlite' => ['dsn' => 'sqlite:' . $file]],
]));

$events = new Dispatcher();
$created = 0;
$events->listen('created', static function () use (&$created): void {
    $created++;
});
Model::setEventDispatcher($events);

$user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password_hash' => 'secret']);
$user->fill(['name' => 'Grace'])->save();

echo "Creado id={$user->id} // eventos 'created': {$created}\n";

$found = User::find($user->id);
echo 'Busqueda: ' . $found->name . ' (' . ($found->exists() ? 'persistido' : 'nuevo') . ")\n";

User::create(['name' => 'Linus', 'email' => 'linus@example.com', 'password_hash' => 'x']);

echo 'Total: ' . count(User::all()) . "\n";
echo 'SQL: ' . User::query()->where('name', 'LIKE', '%ra%')->orderBy('name')->toSql() . "\n";
echo 'Filtrado: ' . count(User::query()->where('name', 'LIKE', '%ra%')->get()) . "\n";

$page = Paginator::offset(User::query(), page: 1, perPage: 1);
echo 'Pagina 1/1: ' . count($page->items) . " registros\n";

echo 'JSON: ' . Resource::make($found)->toJson() . "\n";

try {
    User::create(['name' => 'X', 'email' => 'no-es-email', 'password_hash' => '']);
} catch (\Dr2g\ErpLibs\Exceptions\ValidationException $e) {
    echo 'Validacion: ' . $e->getMessage() . "\n";
}

$cache = new ArrayCache();
$cache->put('users.count', count(User::all()), tags: ['users']);
$cache->flushTags(['users']);
echo 'Cache tras invalidar: ' . var_export($cache->get('users.count'), true) . "\n";

$found->delete();
echo 'Tras borrar: ' . count(User::all()) . "\n";

@unlink($file);

exit(0);
