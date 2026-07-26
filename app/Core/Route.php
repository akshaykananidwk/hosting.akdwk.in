<?php
// FILE: /app/Core/Route.php
// -------------------------------------------------------------------
// Represents a single route: compiles the URI pattern to a regex,
// matches it against the request path and extracts {param} values.
// -------------------------------------------------------------------

namespace App\Core;

class Route
{
    protected array $methods;
    protected string $uri;
    protected mixed $action;
    protected array $middleware;
    protected ?string $name = null;
    protected ?string $compiled = null;
    protected array $paramNames = [];

    public function __construct(array $methods, string $uri, mixed $action, array $middleware = [])
    {
        $this->methods = $methods;
        $this->uri = $uri;
        $this->action = $action;
        $this->middleware = $middleware;
        $this->compile();
    }

    /**
     * Convert `/user/{id}` into a regex and remember param names.
     */
    protected function compile(): void
    {
        $names = [];
        $pattern = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)(\?)?\}/', function ($m) use (&$names) {
            $names[] = $m[1];
            // Optional param support: {id?}
            return isset($m[2]) && $m[2] === '?' ? '(?:/([^/]+))?' : '([^/]+)';
        }, $this->uri);

        $this->paramNames = $names;
        $this->compiled = '#^' . rtrim($pattern, '/') . '/?$#';
    }

    /**
     * Returns extracted params array on match, or null.
     */
    public function matches(string $path): ?array
    {
        if (!preg_match($this->compiled, rtrim($path, '/') ?: '/', $matches)) {
            return null;
        }
        array_shift($matches);
        $params = [];
        foreach ($this->paramNames as $i => $name) {
            $params[$name] = $matches[$i] ?? null;
        }
        return $params;
    }

    public function middleware(string|array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, (array) $middleware);
        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getAction(): mixed
    {
        return $this->action;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
}
