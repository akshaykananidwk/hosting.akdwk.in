<?php
// FILE: /app/Controllers/AuthController.php
// -------------------------------------------------------------------
// MODULE 3 — Authentication: login, logout, register, forgot/reset.
// Rate-limit + lockout (Auth core), audit log, WhatsApp/Email OTP hook.
// -------------------------------------------------------------------

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Crypt;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\WhatsAppService;

class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        return $this->view('auth.login');
    }

    public function login(Request $request): Response
    {
        $data = $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $status = auth()->attempt($data['email'], $data['password']);
        if ($status !== 'ok') {
            $msg = match ($status) {
                'locked' => 'ઘણી વખત ખોટો પ્રયત્ન — 15 મિનિટ માટે એકાઉન્ટ લોક છે.',
                'inactive' => 'તમારું એકાઉન્ટ સક્રિય નથી. સપોર્ટનો સંપર્ક કરો.',
                default => 'ઈમેલ અથવા પાસવર્ડ ખોટો છે.',
            };
            $this->logLogin($data['email'], $status === 'locked' ? 'locked' : 'failed', $msg);
            return back_with('error', $msg);
        }

        $user = db()->table('users')->where('email', $data['email'])->first();

        // 2FA gate.
        if ((int) ($user['two_factor_enabled'] ?? 0) === 1) {
            Session::set('_2fa_user_id', (int) $user['id']);
            if (($user['two_factor_method'] ?? '') === 'whatsapp') {
                $this->sendOtp($user);
            }
            return Response::redirect(url('login/2fa'));
        }

        auth()->login($user);
        $this->logLogin($data['email'], 'success');
        audit('login');
        return Response::redirect(url(dashboard_path()));
    }

    public function logout(Request $request): Response
    {
        audit('logout');
        auth()->logout();
        return redirect_route('login', 'success', 'તમે લોગઆઉટ થઈ ગયા છો.');
    }

    // -----------------------------------------------------------------
    // Registration (client self-signup)
    // -----------------------------------------------------------------

    public function showRegister(Request $request): Response
    {
        return $this->view('auth.register');
    }

    public function register(Request $request): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|min:3',
            'email' => 'required|email|unique:users,email',
            'mobile' => 'required|mobile',
            'password' => 'required|min:8|confirmed',
        ]);

        $tenantId = 1; // System tenant (single-tenant default install).
        $userId = db()->table('users')->insert([
            'tenant_id' => $tenantId,
            'role_id' => 4,
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'],
            'password' => Crypt::hashPassword($data['password']),
            'type' => 'client',
            'status' => 'active',
            'language' => config('app.locale', 'gu'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $names = explode(' ', $data['name'], 2);
        db()->table('clients')->insert([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'client_code' => 'C' . str_pad((string) $userId, 5, '0', STR_PAD_LEFT),
            'first_name' => $names[0],
            'last_name' => $names[1] ?? '',
            'email' => $data['email'],
            'phone' => $data['mobile'],
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $user = db()->table('users')->where('id', $userId)->first();
        auth()->login($user);

        // Welcome WhatsApp.
        (new WhatsAppService())->sendTemplate('welcome', $data['mobile'], [
            'client_name' => $data['name'],
            'company_name' => settings('general.company_name', 'AK Cloud'),
            'panel_url' => url('client'),
        ]);

        return Response::redirect(url('client'));
    }

    // -----------------------------------------------------------------
    // Forgot / reset password
    // -----------------------------------------------------------------

    public function showForgot(Request $request): Response
    {
        return $this->view('auth.forgot');
    }

    public function forgot(Request $request): Response
    {
        $data = $this->validate($request, ['email' => 'required|email']);
        $user = db()->table('users')->where('email', $data['email'])->first();
        if ($user) {
            $token = bin2hex(random_bytes(32));
            db()->table('users')->where('id', (int) $user['id'])->update([
                'reset_token' => hash('sha256', $token),
                'reset_expires_at' => date('Y-m-d H:i:s', time() + 3600),
            ]);
            $link = url('reset?token=' . $token . '&email=' . urlencode($data['email']));
            // Email + WhatsApp.
            db()->table('email_queue')->insert([
                'to_email' => $data['email'],
                'subject' => 'Password Reset — ' . settings('general.company_name', 'AK Cloud'),
                'body' => '<p>Reset link: <a href="' . e($link) . '">' . e($link) . '</a> (1 કલાક માન્ય)</p>',
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if (!empty($user['mobile'])) {
                (new WhatsAppService())->send($user['mobile'], "Password reset link: {$link}");
            }
        }
        // Always show success (no user enumeration).
        return back_with('success', 'જો ઈમેલ રજિસ્ટર્ડ હશે તો reset link મોકલી દીધો છે.');
    }

    public function showReset(Request $request): Response
    {
        return $this->view('auth.reset', [
            'token' => (string) $request->query('token', ''),
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): Response
    {
        $data = $this->validate($request, [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);
        $user = db()->table('users')
            ->where('email', $data['email'])
            ->where('reset_token', hash('sha256', $data['token']))
            ->first();
        if (!$user || empty($user['reset_expires_at']) || strtotime($user['reset_expires_at']) < time()) {
            return back_with('error', 'Reset link અમાન્ય અથવા expire થઈ ગઈ છે.');
        }
        db()->table('users')->where('id', (int) $user['id'])->update([
            'password' => Crypt::hashPassword($data['password']),
            'reset_token' => null, 'reset_expires_at' => null,
            'failed_logins' => 0, 'locked_until' => null,
        ]);
        return redirect_route('login', 'success', 'પાસવર્ડ બદલાઈ ગયો. હવે લોગિન કરો.');
    }

    // -----------------------------------------------------------------
    // 2FA (TOTP or WhatsApp OTP)
    // -----------------------------------------------------------------

    public function show2fa(Request $request): Response
    {
        if (!Session::has('_2fa_user_id')) {
            return Response::redirect(url('login'));
        }
        return $this->view('auth.twofactor');
    }

    public function verify2fa(Request $request): Response
    {
        $userId = (int) Session::get('_2fa_user_id');
        if (!$userId) {
            return Response::redirect(url('login'));
        }
        $code = trim((string) $request->input('code'));
        $user = db()->table('users')->where('id', $userId)->first();
        $ok = false;

        if (($user['two_factor_method'] ?? '') === 'whatsapp') {
            $ok = !empty($user['otp_code']) && hash_equals($user['otp_code'], $code)
                && strtotime($user['otp_expires_at'] ?? '1970-01-01') > time();
        } else {
            $ok = $this->verifyTotp((string) ($user['two_factor_secret'] ?? ''), $code);
        }

        if (!$ok) {
            return back_with('error', 'OTP કોડ ખોટો અથવા expire થયો છે.');
        }
        db()->table('users')->where('id', $userId)->update(['otp_code' => null, 'otp_expires_at' => null]);
        Session::forget('_2fa_user_id');
        auth()->login($user);
        audit('login.2fa');
        return Response::redirect(url(dashboard_path()));
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    protected function sendOtp(array $user): void
    {
        $otp = (string) random_int(100000, 999999);
        db()->table('users')->where('id', (int) $user['id'])->update([
            'otp_code' => $otp,
            'otp_expires_at' => date('Y-m-d H:i:s', time() + 600),
        ]);
        if (!empty($user['mobile'])) {
            (new WhatsAppService())->sendTemplate('otp', $user['mobile'], ['otp' => $otp]);
        }
    }

    /**
     * RFC 6238 TOTP verification (±1 window). base32 secret.
     */
    protected function verifyTotp(string $secret, string $code): bool
    {
        if ($secret === '' || !preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $key = $this->base32Decode($secret);
        $timeSlice = floor(time() / 30);
        for ($i = -1; $i <= 1; $i++) {
            if (hash_equals($this->totpAt($key, (int) ($timeSlice + $i)), $code)) {
                return true;
            }
        }
        return false;
    }

    protected function totpAt(string $key, int $counter): string
    {
        $bin = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $bin, $key, true);
        $offset = ord($hash[19]) & 0xf;
        $value = ((ord($hash[$offset]) & 0x7f) << 24) | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8) | (ord($hash[$offset + 3]) & 0xff);
        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    protected function base32Decode(string $b32): string
    {
        $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32) ?? '');
        $bits = '';
        foreach (str_split($b32) as $c) {
            $bits .= str_pad(decbin(strpos($map, $c)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }

    protected function logLogin(string $email, string $status, string $reason = ''): void
    {
        try {
            db()->table('login_logs')->insert([
                'email' => $email,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                'status' => $status,
                'reason' => $reason ?: null,
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
        }
    }
}
