<?php
// FILE: /app/Core/App.php
// -------------------------------------------------------------------
// Application kernel — config loader + .env parser + minimal service
// container. Central object for the app; all Core services are
// resolved from here.
// -------------------------------------------------------------------

namespace App\Core;

class App
{
    protected static ?App $instance = null;

    protected string $basePath;
    protected array $config = [];
    protected array $env = [];
    protected array $factories = [];   // name => callable
    protected array $services = [];    // name => resolved singleton

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * Boot the framework: load env + config, error handling, timezone,
     * and register core singletons.
     */
    public static function boot(string $basePath): App
    {
        $app = new static($basePath);
        static::$instance = $app;

        $app->loadEnv();
        $app->loadConfig();
        $app->configureErrorHandling();

        date_default_timezone_set($app->config('app.timezone', 'Asia/Kolkata'));
        mb_internal_encoding('UTF-8');

        $app->registerCoreServices();

        return $app;
    }

    public static function instance(): App
    {
        if (static::$instance === null) {
            throw new \RuntimeException('App has not been booted. Call App::boot() first.');
        }
        return static::$instance;
    }

    // ---------------------------------------------------------------
    // .env parsing
    // ---------------------------------------------------------------

    protected function loadEnv(): void
    {
        $file = $this->basePath . '/.env';
        if (!is_file($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Strip surrounding quotes.
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'")) {
                $value = substr($value, 1, -1);
            }
            $this->env[$key] = $value;
            $_ENV[$key] = $value;
        }
    }

    public function env(string $key, mixed $default = null): mixed
    {
        return $this->env[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
    }

    // ---------------------------------------------------------------
    // Config loading (config/config.php with sample fallback)
    // ---------------------------------------------------------------

    protected function loadConfig(): void
    {
        $primary = $this->basePath . '/config/config.php';
        $sample  = $this->basePath . '/config/config.sample.php';

        if (is_file($primary)) {
            $this->config = require $primary;
        } elseif (is_file($sample)) {
            // Not installed yet — boot with sample so /install can run.
            $this->config = require $sample;
        } else {
            $this->config = [];
        }
    }

    /**
     * Dot-notation config accessor: config('db.host').
     */
    public function config(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->config;
        foreach ($segments as $seg) {
            if (is_array($value) && array_key_exists($seg, $value)) {
                $value = $value[$seg];
            } else {
                return $default;
            }
        }
        return $value;
    }

    public function setConfig(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $ref = &$this->config;
        foreach ($segments as $seg) {
            if (!isset($ref[$seg]) || !is_array($ref[$seg])) {
                $ref[$seg] = [];
            }
            $ref = &$ref[$seg];
        }
        $ref = $value;
    }

    public function isDebug(): bool
    {
        return (bool) $this->config('app.debug', false);
    }

    public function isInstalled(): bool
    {
        return is_file($this->basePath . '/install/installed.lock')
            && is_file($this->basePath . '/config/config.php');
    }

    // ---------------------------------------------------------------
    // Path helpers
    // ---------------------------------------------------------------

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path ? '/' . ltrim($path, '/') : '');
    }

    public function storagePath(string $path = ''): string
    {
        return $this->basePath('storage') . ($path ? '/' . ltrim($path, '/') : '');
    }

    public function configPath(string $path = ''): string
    {
        return $this->basePath('config') . ($path ? '/' . ltrim($path, '/') : '');
    }

    public function publicPath(string $path = ''): string
    {
        return $this->basePath('public') . ($path ? '/' . ltrim($path, '/') : '');
    }

    // ---------------------------------------------------------------
    // Tiny service container
    // ---------------------------------------------------------------

    public function singleton(string $name, callable $factory): void
    {
        $this->factories[$name] = $factory;
    }

    public function set(string $name, object $instance): void
    {
        $this->services[$name] = $instance;
    }

    public function make(string $name): object
    {
        if (isset($this->services[$name])) {
            return $this->services[$name];
        }
        if (isset($this->factories[$name])) {
            return $this->services[$name] = ($this->factories[$name])($this);
        }
        throw new \RuntimeException("Service [{$name}] is not registered.");
    }

    public function has(string $name): bool
    {
        return isset($this->services[$name]) || isset($this->factories[$name]);
    }

    protected function registerCoreServices(): void
    {
        $this->singleton('db',      fn() => new Database($this->config('db', [])));
        $this->singleton('cache',   fn() => new Cache($this->storagePath('cache')));
        $this->singleton('logger',  fn() => new Logger($this->storagePath('logs')));
        $this->singleton('events',  fn() => new Event());
        $this->singleton('crypt',   fn() => new Crypt((string) $this->config('app.key', '')));
        $this->singleton('view',    fn() => new View($this->basePath('app/Views')));
        $this->singleton('mail',    fn() => Mail::fromConfig($this->config('mail', [])));
        $this->singleton('auth',    fn() => new Auth());
    }

    // ---------------------------------------------------------------
    // Error handling
    // ---------------------------------------------------------------

    protected function configureErrorHandling(): void
    {
        $debug = $this->isDebug();
        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');

        set_error_handler(function ($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function (\Throwable $e) use ($debug) {
            try {
                $this->make('logger')->exception($e);
            } catch (\Throwable) {
                // Logger unavailable — fall through to output.
            }
            http_response_code($e instanceof HttpException ? $e->getStatusCode() : 500);
            if ($debug) {
                echo '<pre style="padding:20px;font:14px monospace;color:#b00">';
                echo htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8');
                echo '</pre>';
            } else {
                echo '<h1>500 — Server Error</h1><p>Something went wrong. The team has been notified.</p>';
            }
        });
    }
}
