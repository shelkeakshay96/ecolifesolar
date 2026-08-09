<?php

declare(strict_types=1);

namespace EcoLife\Core\App;

use EcoLife\Core\Model\Config;
use ErrorException;
use Throwable;

/**
 * Converts PHP warnings and notices into ErrorException so that nothing is
 * silently ignored, and makes sure a fatal error still produces a log entry.
 *
 * Http::run() is the only place that catches Throwable during a request; this
 * class is the safety net for everything outside that, including errors raised
 * during bootstrap itself.
 */
final class ErrorHandler
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        $developer = Config::isDeveloperMode();

        ini_set('display_errors', $developer ? '1' : '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;   // suppressed with @
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (Throwable $e): void {
            Logger::exception($e);

            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
                exit(1);
            }

            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/plain; charset=UTF-8');
            }
            echo self::fatalBody($e);
        });

        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }

            Logger::error(sprintf('Fatal: %s at %s:%d', $error['message'], $error['file'], $error['line']));

            if (PHP_SAPI !== 'cli' && !headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/plain; charset=UTF-8');
                echo self::genericBody();
            }
        });

        self::$registered = true;
    }

    private static function fatalBody(Throwable $e): string
    {
        if (!Config::isDeveloperMode()) {
            return self::genericBody();
        }

        return sprintf(
            "%s\n\n%s: %s\nat %s:%d\n\n%s\n",
            'Developer mode -- this detail is never shown outside local development.',
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
    }

    private static function genericBody(): string
    {
        return "Something went wrong.\n\nReference: " . Logger::requestId() . "\n";
    }
}
