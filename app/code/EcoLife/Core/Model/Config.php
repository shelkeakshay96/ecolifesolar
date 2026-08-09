<?php

declare(strict_types=1);

namespace EcoLife\Core\Model;

use RuntimeException;

/**
 * Reads app/etc/config.php and app/etc/env.php.
 *
 * File configuration only -- values that differ between machines or that must
 * exist before the database does. Everything the family can change lives in the
 * core_config table instead, reached through Core\Model\Settings.
 */
final class Config
{
    private static array $modules = [];
    private static array $env     = [];
    private static bool  $loaded  = false;

    public static function init(string $etcDir): void
    {
        if (self::$loaded) {
            return;
        }

        $configFile = $etcDir . '/config.php';
        if (!is_file($configFile)) {
            throw new RuntimeException('app/etc/config.php is missing.');
        }
        $config = require $configFile;
        self::$modules = $config['modules'] ?? [];

        $envFile = $etcDir . '/env.php';
        if (!is_file($envFile)) {
            throw new RuntimeException(
                'app/etc/env.php is missing. Copy env.php.sample to env.php and fill it in.'
            );
        }
        self::$env = require $envFile;

        self::$loaded = true;
    }

    /** @return array<string, int> */
    public static function modules(): array
    {
        return self::$modules;
    }

    /**
     * Dot-path lookup into env.php: env('db.host'), env('mail.smtp.port').
     */
    public static function env(string $path, mixed $default = null): mixed
    {
        $value = self::$env;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public static function isDeveloperMode(): bool
    {
        return (bool) self::env('dev.display_errors', false);
    }

    public static function adminFrontName(): string
    {
        $name = (string) self::env('backend.front_name', 'admin');
        return trim($name, '/') ?: 'admin';
    }
}
