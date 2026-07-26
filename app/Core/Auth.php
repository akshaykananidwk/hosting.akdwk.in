<?php
// FILE: /app/Core/Auth.php
// -------------------------------------------------------------------
// Authentication core — session-based identity + credential check
// against the `users` table with lockout support. 2FA/OTP/audit
// logging Module 3 માં આની ઉપર બને છે.
// -------------------------------------------------------------------

namespace App\Core;

class Auth
{
    protected ?array $user = null;
    protected bool $loaded = false;

    /**
     * The currently authenticated user (array from `users`), or null.
     */
    public function user(): ?array
    {
        if ($this->loaded) {
            return $this->user;
        }
        $this->loaded = true;

        $id = Session::get('_auth_user_id');
        if (!$id) {
            return $this->user = null;
        }
        $row = App::instance()->make('db')->table('users')
            ->where('id', (int) $id)
            ->where('status', 'active')
            ->first();
        return $this->user = $row;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function id(): ?int
    {
        $user = $this->user();
        return $user ? (int) $user['id'] : null;
    }

    public function tenantId(): ?int
    {
        $user = $this->user();
        return $user && $user['tenant_id'] !== null ? (int) $user['tenant_id'] : null;
    }

    public function is(string $type): bool
    {
        $user = $this->user();
        return $user !== null && ($user['type'] ?? null) === $type;
    }

    /**
     * Attempt credential login. Returns a status string:
     * 'ok' | 'invalid' | 'locked' | 'inactive'.
     */
    public function attempt(string $email, string $password, ?int $tenantId = null): string
    {
        $db = App::instance()->make('db');
        $query = $db->table('users')->where('email', $email);
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        $user = $query->first();

        if (!$user) {
            return 'invalid';
        }

        // Lockout check.
        if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
            return 'locked';
        }

        if (!Crypt::verifyPassword($password, $user['password'])) {
            $this->registerFailedAttempt($user);
            return 'invalid';
        }

        if (($user['status'] ?? '') !== 'active') {
            return 'inactive';
        }

        $this->clearFailedAttempts($user);
        return 'ok';
    }

    protected function registerFailedAttempt(array $user): void
    {
        $app = App::instance();
        $maxAttempts = (int) $app->config('security.max_login_attempts', 5);
        $lockMinutes = (int) $app->config('security.lockout_minutes', 15);

        $failed = (int) ($user['failed_logins'] ?? 0) + 1;
        $update = ['failed_logins' => $failed, 'updated_at' => date('Y-m-d H:i:s')];
        if ($failed >= $maxAttempts) {
            $update['locked_until'] = date('Y-m-d H:i:s', time() + $lockMinutes * 60);
            $update['failed_logins'] = 0;
        }
        $app->make('db')->table('users')->where('id', (int) $user['id'])->update($update);
    }

    protected function clearFailedAttempts(array $user): void
    {
        App::instance()->make('db')->table('users')->where('id', (int) $user['id'])->update([
            'failed_logins' => 0,
            'locked_until' => null,
        ]);
    }

    /**
     * Establish the authenticated session for a user row.
     */
    public function login(array $user): void
    {
        Session::regenerate();
        Session::set('_auth_user_id', (int) $user['id']);
        Session::regenerateToken();
        $this->user = $user;
        $this->loaded = true;

        App::instance()->make('db')->table('users')->where('id', (int) $user['id'])->update([
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    public function loginUsingId(int $id): bool
    {
        $user = App::instance()->make('db')->table('users')->where('id', $id)->first();
        if (!$user) {
            return false;
        }
        $this->login($user);
        return true;
    }

    public function logout(): void
    {
        Session::forget('_auth_user_id');
        Session::regenerate();
        $this->user = null;
        $this->loaded = true;
    }
}
