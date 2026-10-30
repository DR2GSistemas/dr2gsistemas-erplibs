<?php

namespace Dr2g\ErpLibs\Cache;

/**
 * File-backed cache: one serialised file per key so it survives the request.
 */
class FileCache implements CacheInterface
{
    public function __construct(private readonly string $directory)
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }

    public function put(string $key, mixed $value, ?int $ttl = null, array $tags = []): void
    {
        $payload = serialize([
            'value' => $value,
            'expires' => $ttl === null ? null : time() + $ttl,
            'tags' => $tags,
        ]);

        file_put_contents($this->path($key), $payload);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $entry = $this->read($key);

        return $entry === null ? $default : $entry['value'];
    }

    public function has(string $key): bool
    {
        return $this->read($key) !== null;
    }

    public function forget(string $key): void
    {
        @unlink($this->path($key));
    }

    public function flushTags(array $tags): void
    {
        foreach ($this->keys() as $key) {
            $entry = $this->read($key);

            if ($entry !== null && array_intersect($entry['tags'], $tags) !== []) {
                $this->forget($key);
            }
        }
    }

    public function flush(): void
    {
        foreach ($this->keys() as $key) {
            $this->forget($key);
        }
    }

    /**
     * @return array{value: mixed, expires: ?int, tags: list<string>}|null
     */
    private function read(string $key): ?array
    {
        $path = $this->path($key);

        if (!is_file($path)) {
            return null;
        }

        $entry = @unserialize((string) file_get_contents($path), ['allowed_classes' => false]);

        if (!is_array($entry) || !array_key_exists('value', $entry)) {
            return null;
        }

        if ($entry['expires'] !== null && $entry['expires'] <= time()) {
            $this->forget($key);
            return null;
        }

        /** @var array{value: mixed, expires: int|null, tags: list<string>} $entry */
        return $entry;
    }

    /**
     * @return list<string>
     */
    private function keys(): array
    {
        $keys = [];

        foreach (glob($this->directory . '/*.cache') ?: [] as $file) {
            $keys[] = basename($file, '.cache');
        }

        return $keys;
    }

    private function path(string $key): string
    {
        return $this->directory . '/' . sha1($key) . '.cache';
    }
}
