<?php
// FILE: /app/Core/Crypt.php
// -------------------------------------------------------------------
// AES-256-CBC encryption + HMAC-SHA256 authentication. બધા API keys,
// passwords, tokens આનાથી encrypt થાય. Key `.env` ના APP_KEY માંથી.
// Format: base64( json{ iv, value, mac } ).
// -------------------------------------------------------------------

namespace App\Core;

class Crypt
{
    protected string $key;
    protected string $cipher = 'AES-256-CBC';

    public function __construct(string $key)
    {
        $this->key = $this->normalizeKey($key);
    }

    /**
     * Accept a base64:xxxx key, a raw 32-byte key, or derive one via
     * SHA-256 from any string so the class never breaks on a weak key.
     */
    protected function normalizeKey(string $key): string
    {
        if ($key === '') {
            // No key configured yet (pre-install) — derive an ephemeral one.
            return hash('sha256', 'akcloud-default-insecure-key', true);
        }
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded !== false && strlen($decoded) === 32) {
                return $decoded;
            }
        }
        if (strlen($key) === 32) {
            return $key;
        }
        // Anything else → hash down to exactly 32 bytes.
        return hash('sha256', $key, true);
    }

    /**
     * Generate a fresh app key (base64:...) — used by the installer.
     */
    public static function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(openssl_cipher_iv_length($this->cipher));
        $ciphertext = openssl_encrypt($plaintext, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed.');
        }
        $ivB64 = base64_encode($iv);
        $valueB64 = base64_encode($ciphertext);
        $mac = hash_hmac('sha256', $ivB64 . $valueB64, $this->key);

        $payload = json_encode(['iv' => $ivB64, 'value' => $valueB64, 'mac' => $mac]);
        return base64_encode($payload ?: '');
    }

    /**
     * Decrypt; returns null if the payload is invalid or tampered.
     */
    public function decrypt(string $encrypted): ?string
    {
        $decoded = base64_decode($encrypted, true);
        if ($decoded === false) {
            return null;
        }
        $payload = json_decode($decoded, true);
        if (!is_array($payload) || !isset($payload['iv'], $payload['value'], $payload['mac'])) {
            return null;
        }
        // Verify integrity (constant-time) before decrypting.
        $calc = hash_hmac('sha256', $payload['iv'] . $payload['value'], $this->key);
        if (!hash_equals($calc, (string) $payload['mac'])) {
            return null;
        }
        $iv = base64_decode($payload['iv'], true);
        $value = base64_decode($payload['value'], true);
        if ($iv === false || $value === false) {
            return null;
        }
        $plaintext = openssl_decrypt($value, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv);
        return $plaintext === false ? null : $plaintext;
    }

    /**
     * One-way password hash (bcrypt).
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
