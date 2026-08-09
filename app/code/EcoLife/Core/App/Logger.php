<?php

declare(strict_types=1);

namespace EcoLife\Core\App;

use Throwable;

/**
 * Two log files, appended to directly. No levels beyond what is used, no
 * handlers, no formatters -- a brochure site with a lead form does not need a
 * logging framework, it needs to be able to answer "what happened at 14:32".
 *
 *   var/log/system.log      informational and warnings
 *   var/log/exception.log   uncaught throwables, with a request id
 */
final class Logger
{
    private static ?string $requestId = null;

    public static function requestId(): string
    {
        if (self::$requestId === null) {
            self::$requestId = bin2hex(random_bytes(6));
        }
        return self::$requestId;
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('system.log', 'INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('system.log', 'WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('exception.log', 'ERROR', $message, $context);
    }

    public static function exception(Throwable $e): void
    {
        $message = sprintf(
            "%s: %s\n  at %s:%d\n%s",
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        self::write('exception.log', 'EXCEPTION', $message, [
            'url'    => $_SERVER['REQUEST_URI'] ?? 'cli',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '-',
        ]);

        if ($previous = $e->getPrevious()) {
            self::write('exception.log', 'CAUSED BY', $previous::class . ': ' . $previous->getMessage());
        }
    }

    private static function write(string $file, string $level, string $message, array $context = []): void
    {
        $dir = BP . '/var/log';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;   // Logging must never be the reason a request dies.
        }

        $line = sprintf(
            "[%s] [%s] [%s] %s%s\n",
            date('Y-m-d H:i:s'),
            $level,
            self::requestId(),
            $message,
            $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ''
        );

        @file_put_contents($dir . '/' . $file, $line, FILE_APPEND | LOCK_EX);
    }
}
