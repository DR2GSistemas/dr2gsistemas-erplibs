<?php

/*
 * Auditoría previa a la publicación: lista archivos que entrarían en el
 * paquete de Composer y marca los potencialmente sensibles.
 * Uso: php scripts/release-audit.php
 */

$root = dirname(__DIR__);

// Archivos que viajan en el paquete (respetando .gitattributes export-ignore).
$ignoreFile = $root . '/.gitattributes';
$ignored = [];

if (is_file($ignoreFile)) {
    foreach (file($ignoreFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (str_contains($line, 'export-ignore')) {
            $path = trim(explode(' ', trim($line))[0]);
            $ignored[] = trim($path, '/');
        }
    }
}

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$shipped = [];
$sensitive = [];

$sensitiveNames = ['/.env', '/.env.local', '/composer.lock', '/.phpstan-cache', '/.phpunit.cache'];
$sensitiveExt = ['sqlite', 'sqlite3', 'db', 'log', 'pem', 'key', 'p12', 'pfx'];

foreach ($it as $file) {
    /** @var SplFileInfo $file */
    $relative = '/' . str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

    if (str_starts_with($relative, '/vendor/') || str_starts_with($relative, '/.git/')) {
        continue;
    }

    if ($file->isDir()) {
        continue;
    }

    $shipped[] = $relative;

    $ext = strtolower($file->getExtension());

    $isSensitive = in_array($relative, $sensitiveNames, true)
        || in_array($ext, $sensitiveExt, true)
        || str_contains($relative, '.phpunit.cache')
        || preg_match('/^\/\.env\.(?!template)/', $relative) === 1;

    $isIgnored = false;

    foreach ($ignored as $prefix) {
        if ($prefix !== '' && str_starts_with($relative, '/' . $prefix)) {
            $isIgnored = true;
            break;
        }
    }

    if ($isSensitive && !$isIgnored) {
        $sensitive[] = $relative;
    }
}

sort($shipped);

echo "Archivos en el repositorio (sin vendor/.git): ", count($shipped), PHP_EOL;
echo str_repeat('-', 60), PHP_EOL;

foreach ($shipped as $path) {
    $flag = '  ';

    foreach ($ignored as $prefix) {
        if ($prefix !== '' && str_starts_with($path, '/' . $prefix)) {
            $flag = '⊘ ';
            break;
        }
    }

    echo $flag, $path, PHP_EOL;
}

echo PHP_EOL, 'Sensibles que NO están excluidos del paquete: ', count($sensitive), PHP_EOL;

foreach ($sensitive as $path) {
    echo '  ! ', $path, PHP_EOL;
}

exit($sensitive === [] ? 0 : 1);
