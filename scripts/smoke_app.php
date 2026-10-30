<?php

/*
 * Prueba de humo de una app generada con `new:app`, sin depender de Composer.
 *
 *   php scripts/smoke_app.php [directorio]
 */

use Dr2g\ErpLibs\Routing\Request;
use Dr2g\ErpLibs\View\Renderer;

$app = rtrim(str_replace('\\', '/', $argv[1] ?? dirname(__DIR__) . '/build/newapp'), '/');

if (!is_dir($app)) {
    fwrite(STDERR, "No existe el directorio: {$app}\n");
    exit(2);
}

$lib = dirname(__DIR__) . '/src';

spl_autoload_register(static function (string $class) use ($lib): void {
    $prefix = 'Dr2g\\ErpLibs\\';

    if (str_starts_with($class, $prefix)) {
        $path = $lib . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

        if (is_file($path)) {
            require $path;
        }
    }
});

$composer = json_decode((string) file_get_contents($app . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$psr4 = $composer['autoload']['psr-4'] ?? [];

spl_autoload_register(static function (string $class) use ($app, $psr4): void {
    foreach ($psr4 as $prefix => $relative) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $suffix = str_replace('\\', '/', substr($class, strlen($prefix)));
        $base = $app . '/' . trim($relative, '/');

        // El scaffold organiza por dominio: {Ns}\App\Http\Kernel -> app/Http/Kernel.php
        $candidates = [
            $base . '/' . $suffix . '.php',
            $base . '/' . preg_replace('#^App/#', '', $suffix) . '.php',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                require $path;

                return;
            }
        }
    }
});

$namespace = rtrim((string) array_key_first($psr4), '\\');
$kernelClass = $namespace . '\\App\\Http\\Kernel';
$controllerClass = $namespace . '\\App\\Controllers\\HomeController';

$kernel = new $kernelClass(new Renderer([$app . '/views']), ['debug' => false]);
$kernel->router()->get('/', $controllerClass . '@index');

$failures = 0;
$check = static function (string $name, bool $ok, string $detail = '') use (&$failures): void {
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . ($ok ? '' : ' ' . $detail) . "\n";
    $failures += $ok ? 0 : 1;
};

$home = $kernel->handle(Request::create('GET', '/'));
$check('home responde 200', $home->status === 200, "status {$home->status}");
$check('home usa el controller', str_contains($home->body, 'HomeController@index'));
$check('home aplica el layout', str_contains($home->body, '<!DOCTYPE html>') && str_contains($home->body, 'generado con dr2g erplibs'));

$missing = $kernel->handle(Request::create('GET', '/no-existe'));
$check('404 usa la vista de error', $missing->status === 404 && str_contains($missing->body, 'Página no encontrada'), "status {$missing->status}");

$post = $kernel->handle(Request::create('POST', '/'));
$check('405 usa la vista de error', $post->status === 405 && str_contains($post->body, 'Método no permitido'), "status {$post->status}");

exit($failures === 0 ? 0 : 1);
