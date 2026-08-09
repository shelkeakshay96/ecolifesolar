<?php

declare(strict_types=1);

namespace EcoLife\Core\Module;

/**
 * Flat record of "this module exists, and here is its directory".
 *
 * Every module's registration.php calls register(). Nothing else happens at
 * that point -- no parsing, no ordering, no enabling. ModuleList does that
 * afterwards, which keeps registration.php trivial and side-effect free.
 */
final class ModuleRegistry
{
    /** @var array<string, string> module name => absolute path */
    private static array $paths = [];

    public static function register(string $name, string $path): void
    {
        self::$paths[$name] = rtrim($path, '/');
    }

    /** @return array<string, string> */
    public static function all(): array
    {
        return self::$paths;
    }

    public static function path(string $name): ?string
    {
        return self::$paths[$name] ?? null;
    }

    public static function reset(): void
    {
        self::$paths = [];
    }
}
