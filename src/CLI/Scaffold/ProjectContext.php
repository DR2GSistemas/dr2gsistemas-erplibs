<?php

namespace Dr2g\ErpLibs\CLI\Scaffold;

/**
 * Detecta el proyecto generado desde el directorio actual.
 *
 * Los comandos `make:*` se ejecutan dentro de una app creada con `app:create`;
 * de su `composer.json` se obtiene el namespace raíz y su carpeta PSR-4.
 */
final class ProjectContext
{
    /**
     * @param string $root directorio raíz del proyecto (el del composer.json)
     * @param string $namespace namespace raíz, p. ej. `Acme`
     * @param string $source carpeta absoluta donde viven las clases de $namespace
     */
    private function __construct(
        public readonly string $root,
        public readonly string $namespace,
        public readonly string $source
    ) {
    }

    /**
     * Nombres de archivo donde buscar la raíz del proyecto.
     */
    private const MARKERS = ['composer.json', 'bootstrap.php', '.env'];

    public static function detect(?string $from = null): self
    {
        $root = self::findRoot($from ?? (string) getcwd());

        if ($root === null) {
            throw new \RuntimeException(
                'No se encontró un proyecto generado. Ejecuta el comando desde la raíz de la app '
                . '(junto a composer.json) o usa --path=<proyecto>.'
            );
        }

        $composer = $root . '/composer.json';

        if (!is_file($composer)) {
            throw new \RuntimeException("Falta composer.json en {$root}.");
        }

        $json = json_decode((string) file_get_contents($composer), true);

        if (!is_array($json)) {
            throw new \RuntimeException("composer.json inválido en {$root}.");
        }

        $prefix = self::rootPrefix($json['autoload']['psr-4'] ?? []);

        if ($prefix === null) {
            throw new \RuntimeException('No se encontró el namespace de la app en autoload.psr-4 de composer.json.');
        }

        // PSR-4 admite una ruta o una lista de rutas por prefijo.
        $paths = $json['autoload']['psr-4'][$prefix] ?? 'src';
        $relative = is_array($paths) ? (string) ($paths[0] ?? 'src') : (string) $paths;
        $source = self::absolute($root, $relative);

        return new self($root, rtrim($prefix, '\\'), $source);
    }

    /**
     * Ruta absoluta de un recurso del proyecto (p. ej. `src/routes/web.php`).
     */
    public function path(string $relative): string
    {
        return $this->root . '/' . ltrim($relative, '/');
    }

    /**
     * El prefijo PSR-4 más corto es el namespace raíz de la app.
     *
     * @param array<string, mixed> $psr4
     */
    private static function rootPrefix(array $psr4): ?string
    {
        $prefixes = array_keys(array_filter($psr4, static fn ($path): bool => is_string($path) || is_array($path)));

        if ($prefixes === []) {
            return null;
        }

        usort($prefixes, static fn (string $a, string $b): int => strlen($a) <=> strlen($b));

        return $prefixes[0];
    }

    private static function findRoot(string $from): ?string
    {
        $dir = str_replace('\\', '/', $from);

        while ($dir !== '' && $dir !== '/') {
            foreach (self::MARKERS as $marker) {
                if (is_file($dir . '/' . $marker)) {
                    return $dir;
                }
            }

            $parent = dirname($dir);

            if ($parent === $dir) {
                break;
            }

            $dir = $parent;
        }

        return null;
    }

    private static function absolute(string $root, string $path): string
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');

        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:/#', $path) === 1) {
            return $path;
        }

        return $root . '/' . $path;
    }
}
