<?php

namespace Dr2g\ErpLibs\CLI\Commands;

use Dr2g\ErpLibs\CLI\Command;

/**
 * `db:seed [ClaseSeeder] [--path=<proyecto>]`
 *
 * Ejecuta un seeder del proyecto (por defecto `AuthSeeder`) con la conexión de
 * `src/config/database.php`.
 */
class DbSeedCommand extends Command
{
    public function getName(): string
    {
        return 'db:seed';
    }

    public function getDescription(): string
    {
        return 'Run a database seeder (default: AuthSeeder)';
    }

    /** @param list<string> $arguments */
    public function handle(array $arguments): void
    {
        $class = 'AuthSeeder';
        $path = null;

        foreach ($arguments as $argument) {
            match (true) {
                str_starts_with($argument, '--path=') => $path = substr($argument, 7),
                str_starts_with($argument, '--') => throw new \InvalidArgumentException("Opción desconocida: {$argument}"),
                default => $class = $argument,
            };
        }

        $root = rtrim($path ?? (string) getcwd(), '/\\');

        $autoload = $root . '/vendor/autoload.php';

        if (!is_file($autoload)) {
            throw new \RuntimeException("No se encontró vendor/autoload.php en {$root}. Ejecuta composer install.");
        }

        require_once $autoload;

        // Entorno del proyecto (.env + .env.local)
        if (class_exists(\Dr2g\ErpLibs\Config\DotEnv::class)) {
            \Dr2g\ErpLibs\Config\DotEnv::load($root . '/.env');
            \Dr2g\ErpLibs\Config\DotEnv::load($root . '/.env.local');
        }

        $configFile = $root . '/src/config/database.php';

        if (!is_file($configFile)) {
            throw new \RuntimeException("No se encontró src/config/database.php en {$root}.");
        }

        $config = require $configFile;
        \Dr2g\ErpLibs\Models\Model::setDbManager(new \Dr2g\ErpLibs\Core\DatabaseManager($config));

        $fqcn = str_contains($class, '\\') ? $class : $this->resolveSeederClass($root, $class);

        if (!class_exists($fqcn)) {
            throw new \RuntimeException("No se encontró el seeder {$class} en {$root}/database/seeders.");
        }

        $seeder = new $fqcn();

        if (!is_callable([$seeder, 'run'])) {
            throw new \RuntimeException("El seeder {$fqcn} no define run().");
        }

        $seeder->run();

        echo "[OK] Seeder ejecutado: {$fqcn}\n";
    }

    /**
     * Carga `database/seeders/<Clase>.php` y devuelve su nombre completo.
     *
     * El seeder vive en la raíz del proyecto (no en `src/`), así que no lo cubre
     * el PSR-4: se incluye el archivo y se toma el namespace que declara.
     */
    private function resolveSeederClass(string $root, string $class): string
    {
        $file = $root . '/database/seeders/' . $class . '.php';

        if (!is_file($file)) {
            return $class;
        }

        require_once $file;

        $composer = $root . '/composer.json';
        $json = is_file($composer) ? json_decode((string) file_get_contents($composer), true) : null;
        $prefix = is_array($json) ? (string) array_key_first($json['autoload']['psr-4'] ?? []) : '';

        return rtrim($prefix, '\\') . '\\Database\\Seeders\\' . $class;
    }
}
