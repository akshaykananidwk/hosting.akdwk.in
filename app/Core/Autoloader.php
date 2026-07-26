<?php
// FILE: /app/Core/Autoloader.php
// -------------------------------------------------------------------
// PSR-4 style autoloader (no Composer). Maps the `App\` namespace to
// the `/app/` directory, so no third-party autoloader is
// required.
// -------------------------------------------------------------------

namespace App\Core;

class Autoloader
{
    /** @var array<string,string>  prefix => base directory */
    protected static array $prefixes = [];

    /**
     * Register the SPL autoloader and map the App\ namespace.
     */
    public static function register(string $basePath): void
    {
        self::$prefixes['App\\'] = rtrim($basePath, '/') . '/app/';
        spl_autoload_register([self::class, 'load']);
    }

    /**
     * Add an extra namespace => directory mapping (e.g. for vendor-local libs).
     */
    public static function addNamespace(string $prefix, string $baseDir): void
    {
        self::$prefixes[rtrim($prefix, '\\') . '\\'] = rtrim($baseDir, '/') . '/';
    }

    /**
     * Resolve a fully-qualified class name to a file and require it.
     */
    public static function load(string $class): bool
    {
        foreach (self::$prefixes as $prefix => $baseDir) {
            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                continue;
            }
            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require $file;
                return true;
            }
        }
        return false;
    }
}
