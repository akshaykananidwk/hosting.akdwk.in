<?php
// FILE: /app/Core/HttpException.php
// -------------------------------------------------------------------
// Exception જે HTTP status code સાથે આવે — Router/Controllers આનાથી
// 403/404/419/500 જેવા responses throw કરી શકે.
// -------------------------------------------------------------------

namespace App\Core;

class HttpException extends \RuntimeException
{
    protected int $statusCode;
    protected array $headers;

    public function __construct(int $statusCode = 500, string $message = '', array $headers = [], ?\Throwable $previous = null)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
