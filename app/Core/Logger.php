<?php
// FILE: /app/Core/Logger.php
// -------------------------------------------------------------------
// Daily-rotated file logger — /storage/logs/akcloud-YYYY-MM-DD.log.
// Levels: debug/info/warning/error/critical. Exceptions ને full trace
// સાથે log કરે.
// -------------------------------------------------------------------

namespace App\Core;

class Logger
{
    protected string $path;

    protected array $levels = [
        'debug' => 0, 'info' => 1, 'notice' => 2,
        'warning' => 3, 'error' => 4, 'critical' => 5,
    ];

    public function __construct(string $path)
    {
        $this->path = rtrim($path, '/');
        if (!is_dir($this->path)) {
            @mkdir($this->path, 0775, true);
        }
    }

    protected function file(string $channel = 'akcloud'): string
    {
        return $this->path . '/' . $channel . '-' . date('Y-m-d') . '.log';
    }

    public function log(string $level, string $message, array $context = [], string $channel = 'akcloud'): void
    {
        $level = strtolower($level);
        $line = sprintf(
            "[%s] %s: %s%s%s",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
            PHP_EOL
        );
        @file_put_contents($this->file($channel), $line, FILE_APPEND | LOCK_EX);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /**
     * Log a Throwable with class, message, file:line and trace.
     */
    public function exception(\Throwable $e, string $channel = 'error'): void
    {
        $this->log('error', get_class($e) . ': ' . $e->getMessage(), [
            'file' => $e->getFile() . ':' . $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString()),
        ], $channel);
    }
}
