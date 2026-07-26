<?php
// FILE: /app/Core/Mail.php
// -------------------------------------------------------------------
// Minimal SMTP mailer (Composer/PHPMailer વગર). SSL (465) + STARTTLS
// (587) + AUTH LOGIN support. SMTP host ન હોય તો PHP mail() fallback.
// -------------------------------------------------------------------

namespace App\Core;

class Mail
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'host' => '', 'port' => 587, 'username' => '', 'password' => '',
            'encryption' => 'tls', 'from_email' => '', 'from_name' => 'AK Cloud',
            'timeout' => 30,
        ], $config);
    }

    public static function fromConfig(array $config = []): self
    {
        return new self($config);
    }

    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Send an HTML email. Returns true on success.
     *
     * @param array $options ['from_email','from_name','reply_to','attachments'=>[path,...]]
     */
    public function send(string $to, string $subject, string $htmlBody, array $options = []): bool
    {
        $fromEmail = $options['from_email'] ?? ($this->config['from_email'] ?: 'no-reply@localhost');
        $fromName  = $options['from_name'] ?? $this->config['from_name'];

        if (empty($this->config['host'])) {
            return $this->sendWithPhpMail($to, $subject, $htmlBody, $fromEmail, $fromName, $options);
        }
        try {
            return $this->sendWithSmtp($to, $subject, $htmlBody, $fromEmail, $fromName, $options);
        } catch (\Throwable $e) {
            App::instance()->make('logger')->error('SMTP send failed: ' . $e->getMessage(), ['to' => $to]);
            return false;
        }
    }

    // ---------------------------------------------------------------
    // PHP mail() fallback
    // ---------------------------------------------------------------

    protected function sendWithPhpMail(string $to, string $subject, string $body, string $fromEmail, string $fromName, array $options): bool
    {
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->encodeHeader($fromName) . " <{$fromEmail}>",
        ];
        if (!empty($options['reply_to'])) {
            $headers[] = 'Reply-To: ' . $options['reply_to'];
        }
        return @mail($to, $this->encodeHeader($subject), $body, implode("\r\n", $headers));
    }

    // ---------------------------------------------------------------
    // SMTP implementation (raw sockets)
    // ---------------------------------------------------------------

    protected function sendWithSmtp(string $to, string $subject, string $body, string $fromEmail, string $fromName, array $options): bool
    {
        $enc = strtolower($this->config['encryption']);
        $host = $this->config['host'];
        $port = (int) $this->config['port'];
        $transport = ($enc === 'ssl') ? "ssl://{$host}" : $host;

        $errno = 0;
        $errstr = '';
        $conn = @stream_socket_client(
            "{$transport}:{$port}",
            $errno,
            $errstr,
            (int) $this->config['timeout'],
            STREAM_CLIENT_CONNECT
        );
        if (!$conn) {
            throw new \RuntimeException("SMTP connect failed: {$errstr} ({$errno})");
        }
        stream_set_timeout($conn, (int) $this->config['timeout']);

        $this->expect($conn, [220]);
        $ehloHost = $_SERVER['SERVER_NAME'] ?? 'akcloud.local';
        $this->command($conn, "EHLO {$ehloHost}", [250]);

        if ($enc === 'tls') {
            $this->command($conn, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
                throw new \RuntimeException('STARTTLS negotiation failed.');
            }
            $this->command($conn, "EHLO {$ehloHost}", [250]);
        }

        if ($this->config['username'] !== '') {
            $this->command($conn, 'AUTH LOGIN', [334]);
            $this->command($conn, base64_encode($this->config['username']), [334]);
            $this->command($conn, base64_encode($this->config['password']), [235]);
        }

        $this->command($conn, "MAIL FROM:<{$fromEmail}>", [250]);
        $this->command($conn, "RCPT TO:<{$to}>", [250, 251]);
        $this->command($conn, 'DATA', [354]);

        $message = $this->buildMessage($to, $subject, $body, $fromEmail, $fromName, $options);
        // Dot-stuffing: lines starting with '.' must be escaped.
        $message = preg_replace('/^\./m', '..', $message);
        fwrite($conn, $message . "\r\n.\r\n");
        $this->expect($conn, [250]);

        $this->command($conn, 'QUIT', [221]);
        fclose($conn);
        return true;
    }

    protected function buildMessage(string $to, string $subject, string $body, string $fromEmail, string $fromName, array $options): string
    {
        $boundary = 'akc_' . bin2hex(random_bytes(12));
        $hasAttachments = !empty($options['attachments']);

        $headers = [];
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'From: ' . $this->encodeHeader($fromName) . " <{$fromEmail}>";
        $headers[] = "To: <{$to}>";
        $headers[] = 'Subject: ' . $this->encodeHeader($subject);
        $headers[] = 'Message-ID: <' . bin2hex(random_bytes(10)) . "@{$fromEmail}>";
        $headers[] = 'MIME-Version: 1.0';
        if (!empty($options['reply_to'])) {
            $headers[] = 'Reply-To: ' . $options['reply_to'];
        }

        if (!$hasAttachments) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: base64';
            return implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body));
        }

        $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";
        $parts = "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($body)) . "\r\n";

        foreach ($options['attachments'] as $file) {
            if (!is_file($file)) {
                continue;
            }
            $name = basename($file);
            $content = chunk_split(base64_encode((string) file_get_contents($file)));
            $parts .= "--{$boundary}\r\n"
                . "Content-Type: application/octet-stream; name=\"{$name}\"\r\n"
                . "Content-Transfer-Encoding: base64\r\n"
                . "Content-Disposition: attachment; filename=\"{$name}\"\r\n\r\n"
                . $content . "\r\n";
        }
        $parts .= "--{$boundary}--";

        return implode("\r\n", $headers) . "\r\n\r\n" . $parts;
    }

    protected function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    protected function command($conn, string $command, array $expectedCodes): void
    {
        fwrite($conn, $command . "\r\n");
        $this->expect($conn, $expectedCodes);
    }

    /**
     * Read a (possibly multi-line) SMTP reply and assert its code.
     */
    protected function expect($conn, array $codes): string
    {
        $response = '';
        while (($line = fgets($conn, 515)) !== false) {
            $response .= $line;
            // Multi-line replies use "250-" ; final line uses "250 ".
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new \RuntimeException('Unexpected SMTP reply: ' . trim($response));
        }
        return $response;
    }
}
