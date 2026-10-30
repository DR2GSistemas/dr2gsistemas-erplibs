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
     * @param array{force?: bool, dryRun?: bool, crud?: string|null, api?: bool} $options
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

        $crud = $options['crud'] ?? null;
        $withCrud = $crud !== null && $crud !== '';
        $api = (bool) ($options['api'] ?? false);

        // El flag viaja a todas las plantillas (rutas, .env con SEED_*, etc.).
        if ($api) {
            // Modo API: kernel JSON, sin vistas; el recurso de ejemplo es opcional.
            $files = AppScaffold::files($namespace, trim($name), $this->basePath, $withCrud, api: true);
            $files['src/routes/api.php'] = ApiScaffold::routes($namespace, $withCrud);
            $files['src/Http/ApiKernel.php'] = ApiScaffold::kernel($namespace);
            $files['src/Controllers/Api/HealthController.php'] = ApiScaffold::healthController($namespace);
            $files['src/Http/Middleware/JsonResponse.php'] = ApiScaffold::jsonMiddleware($namespace);
            $files['docs/api.md'] = ApiScaffold::documentation($namespace);
            $files['src/tests/Feature/ApiHealthTest.php'] = ApiScaffold::healthTest($namespace);

            if ($withCrud) {
                // El recurso de ejemplo necesita modelo, migración y soporte de BD.
                $files += CrudScaffold::files($namespace, false);
                $files['src/Controllers/Api/TodoController.php'] = ApiScaffold::resourceController($namespace);
                $files['src/tests/Feature/ApiTodoCrudTest.php'] = ApiScaffold::resourceTest($namespace);
            }
        } else {
            $files = AppScaffold::files($namespace, trim($name), $this->basePath, $withCrud);
        }

        // En modo API el modelo/migración se reutilizan, pero sin vistas ni rutas web.
        if ($withCrud && !$api) {
            $files += match ($crud) {
                'todo' => CrudScaffold::files($namespace, true),
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

        // El autoloader de Composer se derivó del composer.json anterior: si se
        // regeneró con otro namespace, hay que rehacerlo o las clases no cargan.
        if (!($options['dryRun'] ?? false)) {
            $this->refreshAutoloader();
        }

        return $this->created;
    }

    /**
     * Regenera vendor/composer/autoload_*.php desde el composer.json actual.
     *
     * Sólo actúa si ya había dependencias instaladas: en un proyecto nuevo no
     * hacemos red de Composer por el usuario.
     */
    private function refreshAutoloader(): void
    {
        if (!is_file($this->basePath . '/vendor/autoload.php')) {
            return;
        }

        // `--no-cache` es necesario: Composer reutiliza el autoload guardado en
        // vendor/composer/installed.json y no vería el cambio de namespace.
        // El directorio se pasa por cwd (proc_open) para no depender del shell.
        $process = proc_open(
            $this->composerCommand(),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->basePath
        );

        if (!is_resource($process)) {
            $this->warnAboutAutoloader('No se pudo ejecutar composer.');

            return;
        }

        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode === 0) {
            echo "[OK] autoload regenerado (vendor/composer)\n";

            return;
        }

        $this->warnAboutAutoloader($output);
    }

    /**
     * Comando de Composer a ejecutar en el proyecto generado.
     *
     * @return list<string>
     */
    private function composerCommand(): array
    {
        $arguments = ['dump-autoload', '--no-cache', '--no-interaction'];

        // Ruta absoluta del ejecutable: `composer` a secas no sirve como cwd-relative
        // ni resuelve el .bat de Windows desde proc_open.
        $binary = self::findComposerBinary();

        if ($binary !== null) {
            return array_merge($binary, $arguments);
        }

        return array_merge(['composer'], $arguments);
    }

    /**
     * Localiza el ejecutable de Composer (composer.bat/phar en Windows).
     *
     * @return list<string>|null
     */
    private static function findComposerBinary(): ?array
    {
        $names = PHP_OS_FAMILY === 'Windows'
            ? ['composer.bat', 'composer.phar', 'composer.cmd']
            : ['composer'];

        $directories = array_filter(explode(PATH_SEPARATOR, (string) getenv('PATH')));

        foreach ($directories as $directory) {
            foreach ($names as $name) {
                $candidate = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $name;

                if (!is_file($candidate)) {
                    continue;
                }

                // Un .bat necesita cmd; un .phar necesita PHP.
                return match (true) {
                    str_ends_with(strtolower($candidate), '.bat'),
                    str_ends_with(strtolower($candidate), '.cmd') => ['cmd', '/C', $candidate],
                    str_ends_with(strtolower($candidate), '.phar') => [PHP_BINARY, $candidate],
                    default => [$candidate],
                };
            }
        }

        return null;
    }

    /**
     * No es fatal: el proyecto queda generado y el usuario puede reintentarlo.
     */
    private function warnAboutAutoloader(string $detail): void
    {
        fwrite(
            STDERR,
            "Aviso: no se pudo regenerar el autoload de Composer.\n"
            . "Ejecuta: composer dump-autoload --no-cache\n"
            . trim($detail) . "\n"
        );
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
