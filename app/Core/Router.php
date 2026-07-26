<?php
// FILE: /app/Core/Router.php
// -------------------------------------------------------------------
// HTTP router — GET/POST/... routes, {param} placeholders, named
// routes, groups (prefix + middleware), middleware pipeline, and
// dispatch to Closure or "Controller@method".
// -------------------------------------------------------------------

namespace App\Core;

class Router
{
    /** @var array<int,array> */
    protected array $routes = [];
    protected array $names = [];

    /** @var array<string,string>  name => middleware class */
    protected array $middlewareAliases = [];

    // Group context stack.
    protected array $groupStack = [];

    protected string $controllerNamespace = 'App\\Controllers\\';

    public function get(string $uri, mixed $action): Route
    {
        return $this->addRoute(['GET', 'HEAD'], $uri, $action);
    }

    public function post(string $uri, mixed $action): Route
    {
        return $this->addRoute(['POST'], $uri, $action);
    }

    public function put(string $uri, mixed $action): Route
    {
        return $this->addRoute(['PUT'], $uri, $action);
    }

    public function patch(string $uri, mixed $action): Route
    {
        return $this->addRoute(['PATCH'], $uri, $action);
    }

    public function delete(string $uri, mixed $action): Route
    {
        return $this->addRoute(['DELETE'], $uri, $action);
    }

    public function any(string $uri, mixed $action): Route
    {
        return $this->addRoute(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], $uri, $action);
    }

    public function match(array $methods, string $uri, mixed $action): Route
    {
        return $this->addRoute(array_map('strtoupper', $methods), $uri, $action);
    }

    public function aliasMiddleware(string $name, string $class): void
    {
        $this->middlewareAliases[$name] = $class;
    }

    /**
     * Route group: ['prefix'=>'/admin','middleware'=>['auth']].
     */
    public function group(array $attributes, \Closure $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    protected function addRoute(array $methods, string $uri, mixed $action): Route
    {
        $prefix = '';
        $middleware = [];
        foreach ($this->groupStack as $group) {
            if (!empty($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
            if (!empty($group['middleware'])) {
                $middleware = array_merge($middleware, (array) $group['middleware']);
            }
        }
        $uri = '/' . trim($prefix . '/' . trim($uri, '/'), '/');
        $uri = $uri === '' ? '/' : $uri;

        $route = new Route($methods, $uri, $action, $middleware);
        $this->routes[] = $route;
        return $route;
    }

    public function name(string $name, Route $route): void
    {
        $this->names[$name] = $route;
    }

    public function urlFor(string $name, array $params = []): ?string
    {
        foreach ($this->routes as $route) {
            if ($route->getName() === $name) {
                $uri = $route->getUri();
                foreach ($params as $key => $value) {
                    $uri = str_replace('{' . $key . '}', (string) $value, $uri);
                }
                return $uri;
            }
        }
        return null;
    }

    /**
     * Match the request to a route and run the middleware → action pipeline.
     */
    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();

        $methodMatchedButNotUri = false;

        foreach ($this->routes as $route) {
            $params = $route->matches($path);
            if ($params === null) {
                continue;
            }
            if (!in_array($method, $route->getMethods(), true)) {
                $methodMatchedButNotUri = true;
                continue;
            }
            // Turn abort()/authorize()/CSRF HttpExceptions into real responses
            // so controllers can abort(403|404|419) and still get a proper page.
            try {
                return $this->runRoute($route, $request, $params);
            } catch (HttpException $e) {
                return $this->errorResponse($request, $e->getStatusCode(), $e->getMessage())
                    ->withHeaders($e->getHeaders());
            }
        }

        if ($methodMatchedButNotUri) {
            return $this->errorResponse($request, 405, 'Method Not Allowed');
        }
        return $this->errorResponse($request, 404, 'પેજ મળ્યું નહીં (Not Found)');
    }

    protected function runRoute(Route $route, Request $request, array $params): Response
    {
        // Build the middleware pipeline (onion model).
        $core = function (Request $req) use ($route, $params): Response {
            return $this->callAction($route->getAction(), $req, $params);
        };

        $pipeline = array_reduce(
            array_reverse($route->getMiddleware()),
            function (\Closure $next, string $mw): \Closure {
                return function (Request $req) use ($mw, $next): Response {
                    $instance = $this->resolveMiddleware($mw);
                    return $instance->handle($req, $next);
                };
            },
            $core
        );

        return $pipeline($request);
    }

    protected function resolveMiddleware(string $mw): object
    {
        $class = $this->middlewareAliases[$mw] ?? $mw;
        if (!class_exists($class)) {
            throw new \RuntimeException("Middleware [{$mw}] not found.");
        }
        return new $class();
    }

    protected function callAction(mixed $action, Request $request, array $params): Response
    {
        if ($action instanceof \Closure) {
            $result = $action($request, ...array_values($params));
            return $this->toResponse($result);
        }

        if (is_string($action) && str_contains($action, '@')) {
            [$controller, $method] = explode('@', $action, 2);
        } elseif (is_array($action)) {
            [$controller, $method] = $action;
        } else {
            throw new \RuntimeException('Invalid route action.');
        }

        $controller = $this->resolveControllerClass($controller);
        if (!class_exists($controller)) {
            throw new HttpException(500, "Controller [{$controller}] not found.");
        }

        $instance = new $controller();
        if (!method_exists($instance, $method)) {
            throw new HttpException(500, "Method [{$method}] not found on {$controller}.");
        }

        $result = $instance->{$method}($request, ...array_values($params));
        return $this->toResponse($result);
    }

    /**
     * Resolve a route's controller string to a fully-qualified class name.
     *
     * Handles all three forms:
     *   'AuthController'                    -> App\Controllers\AuthController
     *   'Admin\DashboardController'         -> App\Controllers\Admin\DashboardController
     *   '\App\Controllers\Admin\Foo'        -> App\Controllers\Admin\Foo  (already FQ)
     *
     * Public so tooling/tests can verify routes with the exact same logic
     * the dispatcher uses (a checker that re-implements this can pass while
     * the router fails).
     */
    public function resolveControllerClass(string $controller): string
    {
        $controller = ltrim($controller, '\\');
        if (!str_starts_with($controller, ltrim($this->controllerNamespace, '\\'))) {
            $controller = $this->controllerNamespace . $controller;
        }
        return ltrim($controller, '\\');
    }

    protected function toResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }
        if (is_array($result) || is_object($result)) {
            return Response::json($result);
        }
        return Response::make((string) $result);
    }

    protected function errorResponse(Request $request, int $code, string $message): Response
    {
        if ($request->wantsJson()) {
            // A validation abort carries a JSON body already — pass it through.
            $decoded = json_decode($message, true);
            return Response::json(
                is_array($decoded) ? $decoded : ['error' => $message ?: 'Error'],
                $code
            );
        }
        // Prefer a styled error view when one exists for this status code.
        $template = 'errors.' . $code;
        if (view_exists($template)) {
            try {
                return Response::view($template, ['message' => $message], $code);
            } catch (\Throwable) {
                // Fall through to the inline body below.
            }
        }
        $labels = [
            403 => 'પ્રવેશ નથી',
            404 => 'પેજ મળ્યું નહીં',
            405 => 'Method Not Allowed',
            419 => 'સુરક્ષા ટોકન સમાપ્ત',
            429 => 'ઘણી બધી વિનંતીઓ',
        ];
        $title = $labels[$code] ?? 'ભૂલ';
        $body = '<!doctype html><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $code . ' — ' . e($title) . '</title>'
            . '<div style="font-family:system-ui,sans-serif;text-align:center;padding:60px 20px">'
            . '<h1 style="font-size:3rem;margin:0">' . $code . '</h1>'
            . '<h2 style="font-weight:500">' . e($title) . '</h2>'
            . ($message !== '' ? '<p style="color:#64748b">' . e($message) . '</p>' : '')
            . '<p><a href="/" style="color:#2563eb">← હોમ પર જાઓ</a></p></div>';
        return Response::make($body, $code);
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }
}
