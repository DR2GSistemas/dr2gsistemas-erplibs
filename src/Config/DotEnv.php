<?php

namespace Dr2g\ErpLibs\Config;

/**
 * Cargador de archivos `.env` (formato clave=valor, sin dependencias).
 *
 * Reglas:
 *  - `#` al principio de línea o tras un espacio en blanco inicia un comentario.
 *  - Valores entre comillas dobles interpretan `\n`, `\t` y comillas escapadas;
 *    los valores entre comillas simples son literales.
 *  - Las variables existentes en el entorno NO se sobrescriben (permite
 *    `APP_PORT=9000 php bin/app app:serve`).
 */
final class DotEnv
{
    /** @var array<string, string> */
    private array $values = [];

    public function __construct(private readonly string $path)
    {
    }

    /**
     * Carga un `.env` si existe y devuelve los valores leídos.
     *
     * @return array<string, string>
     */
    public static function load(string $path): array
    {
        $env = new self($path);

        return $env->read();
    }

    /**
     * @return array<string, string>
     */
    public function read(): array
    {
        if (!is_file($this->path) || !is_readable($this->path)) {
            return [];
        }

        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }

            $position = strpos($line, '=');

            if ($position === false) {
                continue;
            }

            $key = trim(substr($line, 0, $position));
            $value = $this->clean(substr($line, $position + 1));

            if ($key === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $key) !== 1) {
                continue;
            }

            $this->values[$key] = $value;

            if (getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }

        return $this->values;
    }

    /**
     * @return array<string, string>
     */
    public function values(): array
    {
        return $this->values;
    }

    /**
     * Parsear el `.env.template`: extrae las claves y sus valores por defecto.
     *
     * @return array<string, string>
     */
    public static function templateDefaults(string $path): array
    {
        return (new self($path))->readTemplate();
    }

    /**
     * @return array<string, string>
     */
    private function readTemplate(): array
    {
        if (!is_file($this->path)) {
            return [];
        }

        $defaults = [];

        foreach (file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $defaults[trim($key)] = $this->clean($value);
        }

        return $defaults;
    }

    private function clean(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $first = $value[0];

        if ($first === '"' || $first === "'") {
            $end = strrpos($value, $first);

            if ($end !== false && $end > 0) {
                $inner = substr($value, 1, $end - 1);

                return $first === '"' ? stripcslashes($inner) : $inner;
            }
        }

        // Comentario al final de la línea: `VALOR  # nota`
        if (preg_match('/\s+#/', $value) === 1) {
            $value = (string) substr($value, 0, (int) strpos($value, ' #'));
        }

        return trim($value);
    }
}
