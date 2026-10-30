<?php

namespace Dr2g\ErpLibs\CLI\Commands;

use Dr2g\ErpLibs\CLI\Command;
use Dr2g\ErpLibs\CLI\Scaffold\ProjectContext;

/**
 * Base de los comandos `make:*`: resuelve el proyecto, valida el nombre y
 * escribe el archivo sin sobrescribir nada que ya exista.
 */
abstract class MakeCommand extends Command
{
    /**
     * Ruta relativa del archivo a crear, p. ej. `src/Controllers/UserController.php`.
     *
     * @param array<string, string|bool> $options
     */
    abstract protected function target(ProjectContext $project, string $name, array $options): string;

    /**
     * Contenido del archivo a crear.
     *
     * @param array<string, string|bool> $options
     */
    abstract protected function content(ProjectContext $project, string $name, array $options): string;

    /**
     * Argumento de uso mostrado cuando falta el nombre.
     */
    abstract protected function usage(): string;

    /**
     * Nombre del tipo para los mensajes (`Controller`, `Model`…).
     */
    abstract protected function label(): string;

    /** @param list<string> $arguments */
    public function handle(array $arguments): void
    {
        [$name, $options] = $this->parse($arguments);

        if ($name === null || $name === '') {
            echo $this->usage() . "\n";

            return;
        }

        $project = ProjectContext::detect($this->option($options, 'path'));
        $relative = $this->target($project, $name, $options);
        $path = $project->path($relative);

        if (is_file($path) && !($options['force'] ?? false)) {
            throw new \RuntimeException(
                "Ya existe {$relative}. Usa --force para sobrescribirlo."
            );
        }

        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException("No se pudo crear el directorio {$directory}.");
        }

        file_put_contents($path, $this->content($project, $name, $options));

        echo "[OK] {$this->label()} creado: {$relative}\n";
    }

    /**
     * Separa el nombre (primer argumento sin `--`) de las opciones `--clave=valor`.
     *
     * @param list<string> $arguments
     * @return array{0: ?string, 1: array<string, string|bool>}
     */
    protected function parse(array $arguments): array
    {
        $name = null;
        $options = [];

        foreach ($arguments as $argument) {
            if (!str_starts_with($argument, '--')) {
                $name ??= $argument;

                continue;
            }

            $pair = explode('=', substr($argument, 2), 2);
            $options[$pair[0]] = $pair[1] ?? true;
        }

        return [$name, $options];
    }

    /**
     * Valor de una opción de texto.
     *
     * @param array<string, string|bool> $options
     */
    protected function option(array $options, string $key, ?string $default = null): ?string
    {
        $value = $options[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * Valor de un flag (`--api`, `--resource`).
     *
     * @param array<string, string|bool> $options
     */
    protected function flag(array $options, string $key): bool
    {
        return ($options[$key] ?? false) === true || ($options[$key] ?? null) === 'true';
    }
}
