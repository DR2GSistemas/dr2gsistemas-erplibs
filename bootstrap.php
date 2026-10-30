<?php

/*
 * Bootstrap de la librería. Prefiere el autoloader de Composer (sirve tanto
 * instalada como dependencia como en el monorepo) y cae a un autoloader PSR-4
 * propio para que `php bin/erplibs` funcione tras un simple `git clone`.
 */

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (is_file($autoload)) {
    require_once $autoload;
    return;
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Dr2g\\ErpLibs\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path = __DIR__ . '/src/' . $relative . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});
