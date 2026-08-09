<?php

declare(strict_types=1);

namespace EcoLife\Core\Model;

use Throwable;

/**
 * Reads core_config -- the settings the family can change from the admin panel.
 *
 * Loaded once per request into an array. There are a dozen rows; a cache layer
 * would cost more to maintain than the query it saves.
 *
 * get() never throws. A template asking for a phone number before the database
 * exists (during setup, or on a broken deploy) should render an empty string,
 * not take the whole page down.
 */
final class Settings
{
    /** @var array<string, string|null>|null */
    private static ?array $values = null;

    public static function get(string $path, string $default = ''): string
    {
        $values = self::all();
        $value  = $values[$path] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public static function has(string $path): bool
    {
        return isset(self::all()[$path]);
    }

    /**
     * True when the seeded placeholder is still in place. Step 11 uses this to
     * catch contact details nobody replaced before launch.
     */
    public static function isPlaceholder(string $path): bool
    {
        return str_starts_with(self::get($path), 'PLACEHOLDER');
    }

    /** @return array<string, string|null> */
    public static function all(): array
    {
        if (self::$values !== null) {
            return self::$values;
        }

        self::$values = [];

        try {
            $rows = Db::instance()->query('SELECT path, value FROM core_config');
            foreach ($rows ?: [] as $row) {
                self::$values[$row['path']] = $row['value'];
            }
        } catch (Throwable) {
            // Leave the map empty; callers fall back to their defaults.
        }

        return self::$values;
    }

    public static function save(string $path, ?string $value): void
    {
        $pdo = Db::instance();
        $stmt = $pdo->prepare(
            'INSERT INTO core_config (path, value) VALUES (:path, :value)
             ON DUPLICATE KEY UPDATE value = VALUES(value)'
        );
        $stmt->execute(['path' => $path, 'value' => $value]);

        self::$values[$path] = $value;
    }

    public static function reset(): void
    {
        self::$values = null;
    }
}
