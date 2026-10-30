<?php

namespace Dr2g\ErpLibs\CLI\Scaffold;

/**
 * Genera el esqueleto de una aplicación lista para usar:
 * proyectos con un route, un controller y views con el controller.
 *
 * Escribe en un directorio vacío (o inexistente) y devuelve la lista de
 * archivos creados. No ejecuta `composer install` ni toca archivos.
 */
final class AppGenerator
{
    /** @var list<string> */
    private array $created = [];

    public function __construct(private readonly string $basePath)
    {
    }

    /**
     * @param array{force?: bool, dryRun?: bool} $options
     * @return list<string> rutas relativas creadas
     */
    public function generate(string $namespace, ?string $name = null, array $options = []): array
    {
        $name ??= basename($this->basePath);
        $name = preg_replace('/[^A-Za-z0-9 _-]/', '', $name) ?: 'App';

        $this->assertNamespace($namespace);
        $this->assertTargetIsUsable((bool) ($options['force'] ?? false));

        $files = AppScaffold::files($namespace, trim($name), $this->basePath);

        foreach ($files as $relative => $content) {
            $path = $this->basePath . '/' . $relative;

            if (!($options['dryRun'] ?? false)) {
                $this->ensureDirectory(dirname($path));
                file_put_contents($path, $content);
            }

            $this->created[] = $relative;
        }

        return $this->created;
    }

    private function assertNamespace(string $namespace): void
    {
        if (preg_match('/^[A-Z][A-Za-z0-9]*(\\\\[A-Z][A-Za-z0-9]*)*$/', $namespace) !== 1) {
            throw new \InvalidArgumentException(
                "Namespace inválido: '{$namespace}'. Ejemplo: Acme o Acme\\Erp."
            );
        }
    }

    private function assertTargetIsUsable(bool $force): void
    {
        if (!is_dir($this->basePath) && !$force && is_dir($this->basePath) === false && file_exists($this->basePath)) {
            throw new \RuntimeException("Ya existe un archivo en {$this->basePath}.");
        }

        if (!is_dir($this->basePath)) {
            if (!$force && file_exists($this->basePath)) {
                throw new \RuntimeException("Ya existe un archivo en {$this->basePath}.");
            }

            return;
        }

        $entries = array_diff(scandir($this->basePath) ?: [], ['.', '..', '.git']);

        if ($entries !== [] && !$force) {
            throw new \RuntimeException(
                "El directorio {$this->basePath} no está vacío. Usa --force para sobrescribir."
            );
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException("No se pudo crear el directorio {$directory}.");
        }
    }

    /**
     * Comprueba el scaffold de forma estática sin escribir: usado por el CLI
     * en `--dry-run` y por los tests.
     *
     * @return array<string, string>
     */
    public static function preview(string $namespace, string $name): array
    {
        return AppScaffold::files($namespace, $name, '[path]');
    }
}
