<?php
// FILE: /app/Core/Session.php
// -------------------------------------------------------------------
// Secure session wrapper — httponly/secure/samesite cookies, flash
// data, old input and the CSRF token — exposed as a static facade.
// -------------------------------------------------------------------

namespace App\Core;

class Session
{
    protected static bool $started = false;

    /**
     * Start the session with hardened cookie params.
     */
    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            self::ageFlash();
            return;
        }

        $app = App::instance();
        session_name((string) $app->config('session.name', 'akc_session'));
        session_set_cookie_params([
            'lifetime' => (int) $app->config('session.lifetime', 7200),
            'path' => '/',
            'domain' => '',
            'secure' => (bool) $app->config('session.secure', true),
            'httponly' => (bool) $app->config('session.httponly', true),
            'samesite' => (string) $app->config('session.samesite', 'Lax'),
        ]);
        session_start();
        self::$started = true;

        // Session fixation protection: bind to a fingerprint.
        self::bindFingerprint();
        self::ageFlash();
    }

    protected static function bindFingerprint(): void
    {
        $fingerprint = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|akc');
        if (!isset($_SESSION['_fingerprint'])) {
            $_SESSION['_fingerprint'] = $fingerprint;
        } elseif (!hash_equals($_SESSION['_fingerprint'], $fingerprint)) {
            // Fingerprint changed — drop the session (possible hijack).
            self::flush();
            $_SESSION['_fingerprint'] = $fingerprint;
        }
    }

    /**
     * Move "new" flash keys to the "old" bucket so they survive exactly
     * one subsequent request.
     */
    protected static function ageFlash(): void
    {
        $_SESSION['_flash_old'] = $_SESSION['_flash_new'] ?? [];
        $_SESSION['_flash_new'] = [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_SESSION['_flash_old'] ?? [])) {
            return $_SESSION['_flash_old'][$key];
        }
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]) || isset($_SESSION['_flash_old'][$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key], $_SESSION['_flash_old'][$key]);
    }

    public static function all(): array
    {
        return $_SESSION ?? [];
    }

    public static function flush(): void
    {
        $_SESSION = [];
    }

    // ---------------------------------------------------------------
    // Flash + old input
    // ---------------------------------------------------------------

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash_new'][$key] = $value;
    }

    public static function flashInput(array $input): void
    {
        $_SESSION['_flash_new']['_old_input'] = $input;
    }

    public static function getOldInput(string $key, mixed $default = ''): mixed
    {
        $old = $_SESSION['_flash_old']['_old_input'] ?? [];
        return $old[$key] ?? $default;
    }

    // ---------------------------------------------------------------
    // CSRF token
    // ---------------------------------------------------------------

    public static function token(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    public static function verifyToken(?string $token): bool
    {
        return is_string($token) && !empty($_SESSION['_csrf_token'])
            && hash_equals($_SESSION['_csrf_token'], $token);
    }

    public static function regenerateToken(): void
    {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    /**
     * Regenerate the session id (call after login to prevent fixation).
     */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function id(): string
    {
        return session_id() ?: '';
    }
}
