<?php
// FILE: /app/Core/Response.php
// -------------------------------------------------------------------
// HTTP response object — content, status, headers, cookies. Security
// headers by default. json()/redirect()/view() factory helpers.
// -------------------------------------------------------------------

namespace App\Core;

class Response
{
    protected string $content = '';
    protected int $status = 200;
    protected array $headers = [];
    protected array $cookies = [];

    protected static array $statusTexts = [
        200 => 'OK', 201 => 'Created', 204 => 'No Content',
        301 => 'Moved Permanently', 302 => 'Found', 304 => 'Not Modified',
        400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden',
        404 => 'Not Found', 405 => 'Method Not Allowed', 419 => 'CSRF Token Mismatch',
        422 => 'Unprocessable Entity', 429 => 'Too Many Requests',
        500 => 'Internal Server Error', 503 => 'Service Unavailable',
    ];

    public function __construct(string $content = '', int $status = 200, array $headers = [])
    {
        $this->content = $content;
        $this->status = $status;
        $this->headers = $headers;
    }

    public static function make(string $content = '', int $status = 200, array $headers = []): self
    {
        return new self($content, $status, $headers);
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'application/json; charset=utf-8';
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return new self($body === false ? '{}' : $body, $status, $headers);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public static function view(string $template, array $data = [], int $status = 200): self
    {
        /** @var View $view */
        $view = App::instance()->make('view');
        return new self($view->render($template, $data), $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function status(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function header(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function cookie(string $name, string $value, int $minutes = 0, string $path = '/', bool $httpOnly = true, ?bool $secure = null, string $sameSite = 'Lax'): self
    {
        $this->cookies[] = [
            'name' => $name, 'value' => $value,
            'expires' => $minutes ? time() + ($minutes * 60) : 0,
            'path' => $path, 'httponly' => $httpOnly,
            'secure' => $secure ?? (bool) App::instance()->config('session.secure', true),
            'samesite' => $sameSite,
        ];
        return $this;
    }

    /**
     * Apply recommended security headers (Module SECURITY requirement).
     */
    public function withSecurityHeaders(): self
    {
        $this->headers += [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-XSS-Protection' => '1; mode=block',
        ];
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            $text = self::$statusTexts[$this->status] ?? '';
            header(sprintf('HTTP/1.1 %d %s', $this->status, $text), true, $this->status);

            foreach ($this->headers as $key => $value) {
                header($key . ': ' . $value, true);
            }
            foreach ($this->cookies as $c) {
                setcookie($c['name'], $c['value'], [
                    'expires' => $c['expires'], 'path' => $c['path'],
                    'httponly' => $c['httponly'], 'secure' => $c['secure'],
                    'samesite' => $c['samesite'],
                ]);
            }
        }
        echo $this->content;
    }
}
