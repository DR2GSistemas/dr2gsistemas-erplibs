<?php

namespace Dr2g\ErpLibs\Cache\Redis;

/**
 * Cliente Redis mínimo que habla el protocolo RESP por socket, sin depender de
 * `ext-redis`. Implementa la superficie que necesita `RedisCache`.
 *
 * Uso:
 *   $client = RedisConnection::connect();            // 127.0.0.1:6379
 *   $client = RedisConnection::connect('tcp://redis:6379', 'secret', 2);
 */
final class RedisConnection implements \Dr2g\ErpLibs\Cache\RedisClientInterface, \Dr2g\ErpLibs\Cache\RedisConnectionInterface
{
    /** @var resource */
    private $socket;

    /**
     * @param resource $socket
     */
    private function __construct($socket, public readonly string $dsn)
    {
        $this->socket = $socket;
    }

    public static function connect(?string $dsn = null, ?string $password = null, float $timeout = 1.0): self
    {
        $dsn = $dsn ?? (getenv('REDIS_DSN') ?: 'tcp://127.0.0.1:6379');
        $password = $password ?? (getenv('REDIS_PASSWORD') ?: null);

        if (!str_contains($dsn, '://')) {
            $dsn = 'tcp://' . $dsn;
        }

        $socket = @stream_socket_client($dsn, $errno, $error, $timeout);

        if (!is_resource($socket)) {
            throw new \RuntimeException("Redis connection failed ({$dsn}): {$error} ({$errno}).");
        }

        stream_set_timeout($socket, (int) max(1, $timeout));
        $client = new self($socket, $dsn);

        if ($password !== null && $password !== '') {
            $client->command('AUTH', [$password]);
        }

        $database = getenv('REDIS_DB');

        if ($database !== false && $database !== '') {
            $client->command('SELECT', [(int) $database]);
        }

        return $client;
    }

    /**
     * @param list<mixed> $arguments
     */
    public function command(string $name, array $arguments = []): mixed
    {
        $payload = '*' . (count($arguments) + 1) . self::EOL . self::encode((string) $name);

        foreach ($arguments as $argument) {
            $payload .= self::encode((string) $argument);
        }

        $written = fwrite($this->socket, $payload);

        if ($written === false) {
            throw new \RuntimeException('Redis: no se pudo escribir en el socket.');
        }

        return $this->readReply();
    }

    public function ping(): bool
    {
        return $this->command('PING') === 'PONG';
    }

    public function set(string $key, string $value): mixed
    {
        return $this->command('SET', [$key, $value]);
    }

    public function setex(string $key, int $ttl, string $value): mixed
    {
        return $this->command('SETEX', [$key, $ttl, $value]);
    }

    public function get(string $key): mixed
    {
        return $this->command('GET', [$key]);
    }

    public function exists(string $key): mixed
    {
        return $this->command('EXISTS', [$key]);
    }

    public function del(string $key): mixed
    {
        return $this->command('DEL', [$key]);
    }

    public function sAdd(string $key, string $member): mixed
    {
        return $this->command('SADD', [$key, $member]);
    }

    /**
     * @return list<string>
     */
    public function sMembers(string $key): array
    {
        $reply = $this->command('SMEMBERS', [$key]);

        return is_array($reply) ? array_values(array_map('strval', $reply)) : [];
    }

    /**
     * En producción usar SCAN en lugar de KEYS (bloquea el servidor con muchas claves).
     *
     * @return list<string>
     */
    public function keys(string $pattern): array
    {
        $reply = $this->command('KEYS', [$pattern]);

        return is_array($reply) ? array_values(array_map('strval', $reply)) : [];
    }

    public function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
    }

    private function readReply(): mixed
    {
        $line = fgets($this->socket);

        if ($line === false) {
            throw new \RuntimeException('Redis: conexión cerrada inesperadamente.');
        }

        $type = $line[0];
        $body = substr($line, 1, -2);

        return match ($type) {
            '+' => $body,
            '-' => throw new \RuntimeException('Redis error: ' . $body),
            ':' => (int) $body,
            '$' => $this->readBulk((int) $body),
            '*' => $this->readArray((int) $body),
            default => throw new \RuntimeException('Redis: respuesta desconocida (' . $line . ').'),
        };
    }

    private function readBulk(int $length): ?string
    {
        if ($length === -1) {
            return null;
        }

        $data = '';

        while (strlen($data) < $length + 2) {
            $remaining = $length + 2 - strlen($data);
            $chunk = fread($this->socket, max(1, $remaining));

            if ($chunk === false || $chunk === '') {
                throw new \RuntimeException('Redis: lectura incompleta del valor.');
            }

            $data .= $chunk;
        }

        return substr($data, 0, $length);
    }

    /**
     * @return list<mixed>|null
     */
    private function readArray(int $length): ?array
    {
        if ($length === -1) {
            return null;
        }

        $items = [];

        for ($i = 0; $i < $length; $i++) {
            $items[] = $this->readReply();
        }

        return $items;
    }

    private const EOL = "\r\n";

    private static function encode(string $value): string
    {
        return '$' . strlen($value) . self::EOL . $value . self::EOL;
    }
}
