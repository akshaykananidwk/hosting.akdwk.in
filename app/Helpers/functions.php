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

if (!function_exists('settings')) {
    /**
     * Read a setting from the DB-driven SettingsService (cached).
     * Encrypted settings are transparently decrypted.
     * Falls back to $default if DB/settings are unavailable (e.g. install).
     */
    function settings(?string $key = null, mixed $default = null): mixed
    {
        static $service = null;
        if ($service === null) {
            $service = new \App\Services\SettingsService();
        }
        if ($key === null) {
            return $service;
        }
        return $service->get($key, $default);
    }
}

if (!function_exists('setting_set')) {
    function setting_set(string $key, mixed $value, string $group = 'general', bool $encrypted = false): void
    {
        settings()->set($key, $value, $group, $encrypted);
    }
}

if (!function_exists('money')) {
    // Format an amount with the configured currency symbol (₹1,234.50).
    function money(float|int|string $amount, ?string $symbol = null): string
    {
        $symbol ??= (string) settings('general.currency_symbol', config('app.currency_symbol', '₹'));
        return $symbol . number_format((float) $amount, 2);
    }
}

if (!function_exists('gst_split')) {
    /**
     * Split GST into CGST/SGST (intra-state) or IGST (inter-state) based
     * on whether the client's state code matches the company's.
     *
     * @return array{cgst:float,sgst:float,igst:float,total:float}
     */
    function gst_split(float $taxableAmount, float $ratePercent, bool $sameState): array
    {
        $tax = round($taxableAmount * $ratePercent / 100, 2);
        if ($sameState) {
            $half = round($tax / 2, 2);
            return ['cgst' => $half, 'sgst' => $tax - $half, 'igst' => 0.0, 'total' => $tax];
        }
        return ['cgst' => 0.0, 'sgst' => 0.0, 'igst' => $tax, 'total' => $tax];
    }
}

if (!function_exists('format_bytes')) {
    function format_bytes(int|float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $pow = min($pow, count($units) - 1);
        return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
    }
}

if (!function_exists('format_mb')) {
    function format_mb(int|float $mb): string
    {
        return $mb <= 0 ? 'Unlimited' : format_bytes($mb * 1024 * 1024);
    }
}

if (!function_exists('audit')) {
    /**
     * Write an audit-log entry (best-effort — never throws).
     */
    function audit(string $action, ?string $modelType = null, ?int $modelId = null, array $extra = []): void
    {
        try {
            db()->table('audit_logs')->insert([
                'tenant_id'  => auth()->tenantId(),
                'user_id'    => auth()->id(),
                'action'     => $action,
                'model_type' => $modelType,
                'model_id'   => $modelId,
                'new_values' => $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Auditing must never break the request.
        }
    }
}

if (!function_exists('random_password')) {
    // Generate a strong random password (no ambiguous chars).
    function random_password(int $length = 16): string
    {
        $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789@#%&*';
        $max = strlen($chars) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[random_int(0, $max)];
        }
        return $out;
    }
}

if (!function_exists('akc_username')) {
    /**
     * Build a unique aaPanel/FTP/DB username from a domain.
     * prefix akc_ + cleaned domain, max 16 chars (MySQL user limit).
     */
    function akc_username(string $domain, string $prefix = 'akc_'): string
    {
        $clean = preg_replace('/[^a-z0-9]/', '', strtolower($domain)) ?? '';
        $base = $prefix . substr($clean, 0, 16 - strlen($prefix) - 3);
        // 3 random chars keep it unique across similar domains.
        return substr($base . substr(bin2hex(random_bytes(2)), 0, 3), 0, 16);
    }
}

if (!function_exists('view_exists')) {
    function view_exists(string $template): bool
    {
        return is_file(base_path('app/Views/' . str_replace('.', '/', $template) . '.php'));
    }
}

if (!function_exists('redirect_route')) {
    function redirect_route(string $path, string $flashKey = '', string $flashMsg = ''): \App\Core\Response
    {
        if ($flashKey !== '') {
            \App\Core\Session::flash($flashKey, $flashMsg);
        }
        return \App\Core\Response::redirect(url($path));
    }
}

if (!function_exists('back_with')) {
    function back_with(string $key, string $message): \App\Core\Response
    {
        \App\Core\Session::flash($key, $message);
        return \App\Core\Response::redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }
}

if (!function_exists('is_active')) {
    // Return 'active' when the current request path matches a prefix.
    function is_active(string $prefix, string $class = 'active'): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        return str_starts_with(rtrim($path, '/') . '/', rtrim($prefix, '/') . '/') ? $class : '';
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        return auth()->user();
    }
}

if (!function_exists('initials')) {
    function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
        return mb_strtoupper($first . $last) ?: 'U';
    }
}

if (!function_exists('dashboard_path')) {
    // Resolve the correct dashboard base path for the current user type.
    function dashboard_path(): string
    {
        return match (auth()->user()['type'] ?? 'client') {
            'super_admin', 'tenant_owner', 'staff' => 'admin',
            'reseller' => 'reseller',
            default => 'client',
        };
    }
}
