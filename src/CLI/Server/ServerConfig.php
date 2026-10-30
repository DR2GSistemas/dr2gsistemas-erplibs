<?php

namespace Dr2g\ErpLibs\CLI\Server;

/**
 * Construye y localiza el comando del servidor embebido de PHP para un
 * proyecto de aplicación (`public/index.php` + `public/` como docroot).
 *
 * Separado del comando CLI para poder probarlo sin arrancar un proceso.
 */
final class ServerConfig
{
    public const DEFAULT_HOST = '127.0.0.1';
    public const DEFAULT_PORT = 8000;

    /**
     * @param string $project ruta al proyecto (donde está public/index.php)
     */
    public function __construct(
        public readonly string $project,
        public readonly string $host = self::DEFAULT_HOST,
        public readonly int $port = self::DEFAULT_PORT,
        public readonly ?string $router = null
    ) {
    }

    /**
     * Analiza los argumentos: `[--port=8002] [--host=0.0.0.0] [--router=archivo] [proyecto]`.
     *
     * @param list<string> $arguments
     */
    public static function fromArguments(string $cwd, array $arguments): self
    {
        $project = $cwd;
        $host = self::DEFAULT_HOST;
        $port = self::DEFAULT_PORT;
        $router = null;

        foreach ($arguments as $index => $argument) {
            $next = $arguments[$index + 1] ?? null;

            match (true) {
                str_starts_with($argument, '--port=') => $port = (int) substr($argument, 7),
                $argument === '--port' => $port = (int) self::valueAfter($arguments, $index, '--port'),
                str_starts_with($argument, '--host=') => $host = substr($argument, 7),
                $argument === '--host' => $host = (string) self::valueAfter($arguments, $index, '--host'),
                str_starts_with($argument, '--router=') => $router = substr($argument, 9),
                $argument === '--router' => $router = (string) self::valueAfter($arguments, $index, '--router'),
                $argument === '--help' || $argument === '-h' => null,
                // Valor consumido por la opción anterior (--port 8002)
                $index > 0 && in_array($arguments[$index - 1], ['--port', '--host', '--router'], true) => null,
                str_starts_with($argument, '--') => throw new \InvalidArgumentException("Opción desconocida: {$argument}"),
                default => $project = $argument,
            };
        }

        if ($port < 1 || $port > 65535) {
            throw new \InvalidArgumentException("Puerto inválido: {$port}. Debe estar entre 1 y 65535.");
        }

        if ($host === '') {
            throw new \InvalidArgumentException('Host inválido.');
        }

        return new self(rtrim($project, '/\\'), $host, $port, $router);
    }

    /**
     * Valor que sigue a `--port`/`--host`/`--router` (`--port 8002`).
     *
     * @param list<string> $arguments
     */
    private static function valueAfter(array $arguments, int $index, string $option): string
    {
        $value = $arguments[$index + 1] ?? null;

        if ($value === null || str_starts_with($value, '--')) {
            throw new \InvalidArgumentException("Falta el valor de {$option}.");
        }

        return $value;
    }

    public function docroot(): string
    {
        return $this->project . '/public';
    }

    public function routerFile(): string
    {
        if ($this->router !== null) {
            return str_starts_with($this->router, '/') || preg_match('#^[A-Za-z]:/#', $this->router) === 1
                ? $this->router
                : $this->project . '/' . ltrim($this->router, '/');
        }

        return $this->docroot() . '/index.php';
    }

    public function url(): string
    {
        $displayHost = in_array($this->host, ['0.0.0.0', '::'], true) ? 'localhost' : $this->host;

        return "http://{$displayHost}:{$this->port}";
    }

    /**
     * Verifica que el proyecto se pueda servir.
     */
    public function validate(): void
    {
        if (!is_file($this->project . '/public/index.php')) {
            throw new \RuntimeException(
                "No se encontró public/index.php en {$this->project}. Ejecuta app:serve desde la raíz de una app generada o pasa la ruta."
            );
        }

        if (!is_file($this->routerFile())) {
            throw new \RuntimeException("No se encontró el router: {$this->routerFile()}.");
        }
    }

    /**
     * Argumentos de `php -S`.
     *
     * @return list<string>
     */
    public function command(): array
    {
        return [
            (string) (getenv('PHP_BINARY') ?: PHP_BINARY),
            '-S',
            "{$this->host}:{$this->port}",
            '-t',
            $this->docroot(),
            $this->routerFile(),
        ];
    }

    /**
     * ¿Hay algo escuchando ya en el puerto? (evita arrancar dos servidores).
     */
    public function portIsInUse(): bool
    {
        $host = $this->host === '0.0.0.0' || $this->host === '::' ? '127.0.0.1' : $this->host;

        // connect() sólo devuelve true si hay un listener; un socket escuchando
        // sin backlog no admite conexiones y eso no es "en uso" para nosotros.
        $client = @stream_socket_client("tcp://{$host}:{$this->port}", $errno, $error, 0.3, STREAM_CLIENT_CONNECT);

        if (is_resource($client)) {
            fclose($client);

            return true;
        }

        return false;
    }
}
