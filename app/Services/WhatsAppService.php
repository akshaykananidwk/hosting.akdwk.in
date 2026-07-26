<?php
// FILE: /app/Services/WhatsAppService.php
// -------------------------------------------------------------------
// 🟢 MODULE 9 — WhatsApp integration via bulk.akdwk.in gateway.
// Number formatting, template rendering, DB queue, rate limits, logs.
// api_key / session_id come from `settings` (encrypted) — never hardcoded.
// -------------------------------------------------------------------

namespace App\Services;

use App\Core\App;

class WhatsAppService
{
    protected string $apiUrl;
    protected string $apiKey;
    protected string $sessionId;

    public function __construct()
    {
        $this->apiUrl = (string) settings('whatsapp.api_url', config('whatsapp.api_url', 'https://bulk.akdwk.in/api.php'));
        $this->apiKey = (string) settings('whatsapp.api_key', '');
        $this->sessionId = (string) settings('whatsapp.session_id', '');
    }

    /**
     * Normalize an Indian mobile number to gateway format (91XXXXXXXXXX).
     * Returns null for invalid numbers.
     */
    public function formatNumber(string $number): ?string
    {
        // Strip +, spaces, dashes, parentheses.
        $n = preg_replace('/[^0-9]/', '', $number) ?? '';
        // Remove leading zeros.
        $n = ltrim($n, '0');
        if ($n === '') {
            return null;
        }
        // Already has 91 prefix (12 digits) → keep.
        if (strlen($n) === 12 && str_starts_with($n, '91')) {
            $local = substr($n, 2);
            return $this->isValidLocal($local) ? $n : null;
        }
        // 10-digit local → add 91.
        if (strlen($n) === 10 && $this->isValidLocal($n)) {
            return '91' . $n;
        }
        return null;
    }

    protected function isValidLocal(string $local): bool
    {
        return preg_match('/^[6-9]\d{9}$/', $local) === 1;
    }

    /**
     * Send immediately via cURL (used by the queue worker).
     *
     * @return array{ok:bool,response:string}
     */
    public function send(string $number, string $message, ?string $mediaUrl = null): array
    {
        $formatted = $this->formatNumber($number);
        if ($formatted === null) {
            $this->log($number, $message, $mediaUrl, false, 'invalid number');
            return ['ok' => false, 'response' => 'invalid number'];
        }
        if ($this->apiKey === '' || $this->sessionId === '') {
            $this->log($formatted, $message, $mediaUrl, false, 'whatsapp not configured');
            return ['ok' => false, 'response' => 'not configured'];
        }

        $payload = [
            'api_key' => $this->apiKey,
            'number' => $formatted,
            'message' => $message,
            'session_id' => $this->sessionId,
        ];
        if ($mediaUrl !== null && $mediaUrl !== '') {
            $payload['media_url'] = $mediaUrl;
        }

        $response = '';
        $ok = false;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $ch = curl_init($this->apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $response = (string) curl_exec($ch);
            $errno = curl_errno($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($errno === 0 && $httpCode >= 200 && $httpCode < 300) {
                $ok = true;
                break;
            }
            if ($attempt < 3) {
                usleep($attempt * 500000);
            }
        }

        $this->log($formatted, $message, $mediaUrl, $ok, $response);
        return ['ok' => $ok, 'response' => $response];
    }

    /**
     * Queue a message (preferred) — the cron worker delivers with rate limits.
     */
    public function queue(string $number, string $message, ?string $mediaUrl = null, ?string $scheduledAt = null, ?int $campaignId = null): int
    {
        return App::instance()->make('db')->table('whatsapp_queue')->insert([
            'tenant_id' => auth()->tenantId(),
            'number' => $number,
            'message' => $message,
            'media_url' => $mediaUrl,
            'campaign_id' => $campaignId,
            'status' => 'pending',
            'scheduled_at' => $scheduledAt,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Render a template by slug, replacing {variables}, then queue it.
     */
    public function sendTemplate(string $slug, string $number, array $vars = [], ?string $mediaUrl = null): int|false
    {
        $tpl = App::instance()->make('db')->table('whatsapp_templates')
            ->where('slug', $slug)
            ->where('is_active', 1)
            ->first();
        if (!$tpl) {
            return false;
        }
        $message = $this->render($tpl['body'], $vars);
        return $this->queue($number, $message, $mediaUrl);
    }

    /**
     * Replace {placeholders} with values.
     */
    public function render(string $body, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $body = str_replace('{' . $key . '}', (string) $value, $body);
        }
        return $body;
    }

    protected function log(string $number, string $message, ?string $mediaUrl, bool $ok, string $response, ?string $event = null): void
    {
        try {
            App::instance()->make('db')->table('whatsapp_logs')->insert([
                'tenant_id' => auth()->tenantId(),
                'number' => $number,
                'message' => mb_substr($message, 0, 4000),
                'media_url' => $mediaUrl,
                'status' => $ok ? 'sent' : 'failed',
                'response' => mb_substr($response, 0, 2000),
                'event' => $event,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // never break on logging
        }
    }

    // ---------------------------------------------------------------
    // Rate-limit helpers (used by the queue worker)
    // ---------------------------------------------------------------

    public function withinRateLimit(): bool
    {
        $db = App::instance()->make('db');
        $hourly = (int) settings('whatsapp.hourly_max', 200);
        $daily = (int) settings('whatsapp.daily_max', 1000);

        $sentHour = $db->table('whatsapp_logs')
            ->where('status', 'sent')
            ->where('created_at', '>=', date('Y-m-d H:i:s', time() - 3600))
            ->count();
        if ($sentHour >= $hourly) {
            return false;
        }
        $sentDay = $db->table('whatsapp_logs')
            ->where('status', 'sent')
            ->where('created_at', '>=', date('Y-m-d 00:00:00'))
            ->count();
        return $sentDay < $daily;
    }

    public function randomDelaySeconds(): int
    {
        $min = (int) settings('whatsapp.delay_min', 3);
        $max = (int) settings('whatsapp.delay_max', 8);
        return random_int(min($min, $max), max($min, $max));
    }
}
