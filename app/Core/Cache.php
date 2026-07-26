<?php
// FILE: /app/Core/Cache.php
// -------------------------------------------------------------------
// File-based cache — /storage/cache માં serialized files. TTL support,
// remember() helper, atomic writes. Redis જેવી dependency વગર.
// -------------------------------------------------------------------

namespace App\Core;

class Cache
{
    protected string $path;

    public function __construct(string $path)
    {
        $this->path = rtrim($path, '/');
        if (!is_dir($this->path)) {
            @mkdir($this->path, 0775, true);
        }
    }

    protected function fileFor(string $key): string
    {
        return $this->path . '/' . sha1($key) . '.cache';
    }

    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    /**
     * @param mixed $default returned when the key is missing/expired.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->fileFor($key);
        if (!is_file($file)) {
            return $default;
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return $default;
        }
        $data = @unserialize($raw);
        if (!is_array($data) || !array_key_exists('expires', $data)) {
            return $default;
        }
        if ($data['expires'] !== 0 && $data['expires'] < time()) {
            @unlink($file);
            return $default;
        }
        return $data['value'];
    }

    /**
     * @param int $ttl seconds; 0 = forever.
     */
    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        $payload = serialize([
            'expires' => $ttl === 0 ? 0 : time() + $ttl,
            'value' => $value,
        ]);
        $file = $this->fileFor($key);
        // Atomic write via temp file + rename.
        $tmp = $file . '.' . uniqid('', true) . '.tmp';
        if (@file_put_contents($tmp, $payload, LOCK_EX) === false) {
            return false;
        }
        return @rename($tmp, $file);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->put($key, $value, 0);
    }

    public function forget(string $key): bool
    {
        $file = $this->fileFor($key);
        return is_file($file) ? @unlink($file) : true;
    }

    public function flush(): void
    {
        foreach (glob($this->path . '/*.cache') ?: [] as $file) {
            @unlink($file);
        }
    }

    /**
     * Return cached value or compute+store it.
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $sentinel = new \stdClass();
        $value = $this->get($key, $sentinel);
        if ($value !== $sentinel) {
            return $value;
        }
        $value = $callback();
        $this->put($key, $value, $ttl);
        return $value;
    }

    public function increment(string $key, int $by = 1): int
    {
        $value = (int) $this->get($key, 0) + $by;
        $this->forever($key, $value);
        return $value;
    }

    public function decrement(string $key, int $by = 1): int
    {
        return $this->increment($key, -$by);
    }
}
