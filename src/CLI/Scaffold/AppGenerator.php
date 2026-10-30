<?php

namespace Dr2g\ErpLibs\CLI\Scaffold;

/**
 * Orquesta la creación de una aplicación: proyecto base + CRUD de ejemplo.
 *
 * `app:create <nombre>` crea `<nombre>/` en el directorio actual con el
 * scaffold conectado a la librería y, con `--crud=todo`, un CRUD de todolist
 * listo para probar.
 */
final class AppGenerator
{
    /** @var list<string> */
    private array $created = [];

    public function __construct(private readonly string $basePath)
    {
    }

    /**
     * @param array{force?: bool, dryRun?: bool, crud?: string|null} $options
     * @return list<string> rutas relativas creadas
     */
    public function generate(?string $namespace = null, ?string $name = null, array $options = []): array
    {
        // Con nombre de proyecto explícito se ignora el sufijo aleatorio del temp dir.
        $namespace ??= self::defaultNamespace($name ?? basename($this->basePath));
        $name ??= basename($this->basePath);
        $name = (string) preg_replace('/[^A-Za-z0-9 _-]/', '', $name) ?: 'App';

        $this->assertNamespace($namespace);
        $this->assertTargetIsUsable((bool) ($options['force'] ?? false));

        // La librería se referencia por ruta cuando existe en este checkout.
        AppScaffold::useLibraryPath($this->basePath);

        $files = AppScaffold::files($namespace, trim($name), $this->basePath);

        $crud = $options['crud'] ?? null;

        if ($crud !== null && $crud !== '') {
            $files['routes/web.php'] = AppScaffold::routes($namespace, true);
        }

        if ($crud !== null && $crud !== '') {
            $files += match ($crud) {
                'todo' => CrudScaffold::files($namespace),
                default => throw new \InvalidArgumentException("CRUD no soportado: '{$crud}'. Disponibles: todo."),
            };
        }

        foreach ($files as $relative => $content) {
            $path = $this->basePath . '/' . $relative;

            if (!($options['dryRun'] ?? false)) {
                $this->ensureDirectory(dirname($path));
                file_put_contents($path, $content);

                if ($relative === 'bin/app') {
                    @chmod($path, 0755);
                }
            }

            $this->created[] = $relative;
        }

        return $this->created;
    }

    /**
     * `proyect001` → `Proyect001`; un separador genera sub-namespace.
     */
    public static function defaultNamespace(string $name): string
    {
        $parts = preg_split('/[^A-Za-z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: ['App'];

        $parts = array_map(
            static fn (string $part): string => ucfirst(strtolower($part)),
            $parts
        );

        return implode('\\', $parts);
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
        if (!is_dir($this->basePath)) {
            if (file_exists($this->basePath)) {
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
