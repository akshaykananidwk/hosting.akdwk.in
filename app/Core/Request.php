<?php
// FILE: /app/Core/Request.php
// -------------------------------------------------------------------
// HTTP request wrapper bringing $_GET/$_POST/$_SERVER/body together in
// one place. Method spoofing, JSON body, headers, IP, AJAX detection.
// -------------------------------------------------------------------

namespace App\Core;

class Request
{
    protected array $query;
    protected array $request;   // POST / form
    protected array $server;
    protected array $cookies;
    protected array $files;
    protected array $json = [];
    protected string $method;
    protected string $path;

    /** Arbitrary per-request attributes set by middleware (e.g. api_key). */
    public array $attributes = [];

    public function __construct(array $query, array $request, array $server, array $cookies, array $files, string $rawBody = '')
    {
        $this->query   = $query;
        $this->request = $request;
        $this->server  = $server;
        $this->cookies = $cookies;
        $this->files   = $files;

        // Parse JSON body if content-type is JSON.
        $contentType = $server['CONTENT_TYPE'] ?? '';
        if ($rawBody !== '' && str_contains($contentType, 'application/json')) {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $this->json = $decoded;
            }
        }

        $this->method = $this->resolveMethod();
        $this->path   = $this->resolvePath();
    }

    public static function capture(): self
    {
        return new self($_GET, $_POST, $_SERVER, $_COOKIE, $_FILES, file_get_contents('php://input') ?: '');
    }

    protected function resolveMethod(): string
    {
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'POST') {
            $override = $this->request['_method'] ?? ($this->server['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? null);
            if ($override) {
                $method = strtoupper($override);
            }
        }
        return $method;
    }

    protected function resolvePath(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
        // Strip a script-dir prefix if the app is not at the domain root.
        $scriptDir = rtrim(str_replace('\\', '/', dirname($this->server['SCRIPT_NAME'] ?? '')), '/');
        if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($uri, $scriptDir)) {
            $uri = substr($uri, strlen($scriptDir));
        }
        $uri = '/' . trim($uri, '/');
        return $uri === '' ? '/' : $uri;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function fullUrl(): string
    {
        $scheme = ($this->server['HTTPS'] ?? '') === 'on' ? 'https' : 'http';
        $host = $this->server['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . ($this->server['REQUEST_URI'] ?? '/');
    }

    /**
     * Unified input accessor: JSON body → POST → GET.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->json[$key] ?? $this->request[$key] ?? $this->query[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->request[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->request, $this->json);
    }

    public function only(array $keys): array
    {
        $result = [];
        $all = $this->all();
        foreach ($keys as $key) {
            if (array_key_exists($key, $all)) {
                $result[$key] = $all[$key];
            }
        }
        return $result;
    }

    public function except(array $keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function filled(string $key): bool
    {
        $value = $this->input($key);
        return $value !== null && $value !== '';
    }

    public function boolean(string $key): bool
    {
        return filter_var($this->input($key), FILTER_VALIDATE_BOOLEAN);
    }

    public function header(string $key, mixed $default = null): mixed
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $this->server[$key] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization', '');
        if (is_string($header) && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return null;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $header) {
            if (!empty($this->server[$header])) {
                $ip = explode(',', $this->server[$header])[0];
                return trim($ip);
            }
        }
        return '0.0.0.0';
    }

    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    public function isAjax(): bool
    {
        return strtolower($this->server['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }

    public function wantsJson(): bool
    {
        $accept = $this->server['HTTP_ACCEPT'] ?? '';
        return $this->isAjax() || str_contains($accept, 'application/json') || !empty($this->json);
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->json;
        }
        return $this->json[$key] ?? $default;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
