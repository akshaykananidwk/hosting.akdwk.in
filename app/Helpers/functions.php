<?php
// FILE: /app/Helpers/functions.php
// -------------------------------------------------------------------
// Global helper functions — આખી app માં વપરાય. બધા `function_exists`
// guard સાથે જેથી double-include પર ભૂલ ન આવે.
// -------------------------------------------------------------------

use App\Core\App;
use App\Core\Auth;
use App\Core\Cache;
use App\Core\Crypt;
use App\Core\Database;
use App\Core\Event;
use App\Core\Logger;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

if (!function_exists('app')) {
    function app(?string $service = null): mixed
    {
        $app = App::instance();
        return $service === null ? $app : $app->make($service);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return App::instance()->config($key, $default);
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return App::instance()->env($key, $default);
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return App::instance()->basePath($path);
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return App::instance()->storagePath($path);
    }
}

if (!function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return App::instance()->publicPath($path);
    }
}

if (!function_exists('db')) {
    function db(): Database
    {
        /** @var Database */
        return App::instance()->make('db');
    }
}

if (!function_exists('cache')) {
    function cache(): Cache
    {
        /** @var Cache */
        return App::instance()->make('cache');
    }
}

if (!function_exists('logger')) {
    function logger(): Logger
    {
        /** @var Logger */
        return App::instance()->make('logger');
    }
}

if (!function_exists('events')) {
    function events(): Event
    {
        /** @var Event */
        return App::instance()->make('events');
    }
}

if (!function_exists('crypt_service')) {
    function crypt_service(): Crypt
    {
        /** @var Crypt */
        return App::instance()->make('crypt');
    }
}

if (!function_exists('encrypt')) {
    function encrypt(string $value): string
    {
        return crypt_service()->encrypt($value);
    }
}

if (!function_exists('decrypt')) {
    function decrypt(string $value): ?string
    {
        return crypt_service()->decrypt($value);
    }
}

if (!function_exists('auth')) {
    function auth(): Auth
    {
        /** @var Auth */
        return App::instance()->make('auth');
    }
}

if (!function_exists('session')) {
    /**
     * session()            -> Session facade class name usage discouraged;
     * session('key')       -> get
     * session(['k'=>'v'])  -> set many
     */
    function session(string|array|null $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return null;
        }
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                Session::set($k, $v);
            }
            return true;
        }
        return Session::get($key, $default);
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = []): string
    {
        /** @var View $view */
        $view = App::instance()->make('view');
        return $view->render($template, $data);
    }
}

if (!function_exists('e')) {
    // HTML-escape for safe output (XSS protection).
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }
}

if (!function_exists('json_response')) {
    function json_response(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim((string) config('app.url', ''), '/');
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('public/assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Session::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        return Session::getOldInput($key, $default);
    }
}

if (!function_exists('now')) {
    function now(string $format = 'Y-m-d H:i:s'): string
    {
        return date($format);
    }
}

if (!function_exists('str_random')) {
    function str_random(int $length = 16): string
    {
        $bytes = random_bytes((int) ceil($length / 2));
        return substr(bin2hex($bytes), 0, $length);
    }
}

if (!function_exists('slugify')) {
    function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        return trim($text, '-');
    }
}

if (!function_exists('array_get')) {
    function array_get(array $array, string $key, mixed $default = null): mixed
    {
        foreach (explode('.', $key) as $seg) {
            if (is_array($array) && array_key_exists($seg, $array)) {
                $array = $array[$seg];
            } else {
                return $default;
            }
        }
        return $array;
    }
}

if (!function_exists('__')) {
    // Translation helper — lang/{locale}.php માંથી key વાંચે.
    function __(string $key, array $replace = []): string
    {
        static $lines = null;
        if ($lines === null) {
            $locale = (string) config('app.locale', 'gu');
            $file = base_path("lang/{$locale}.php");
            $lines = is_file($file) ? (array) require $file : [];
        }
        $text = $lines[$key] ?? $key;
        foreach ($replace as $k => $v) {
            $text = str_replace(':' . $k, (string) $v, $text);
        }
        return $text;
    }
}

if (!function_exists('abort')) {
    function abort(int $code, string $message = ''): void
    {
        throw new \App\Core\HttpException($code, $message);
    }
}
