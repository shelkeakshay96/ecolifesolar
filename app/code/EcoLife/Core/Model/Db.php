<?php

declare(strict_types=1);

namespace EcoLife\Core\Model;

use PDO;
use PDOException;
use RuntimeException;

/**
 * The PDO connection.
 *
 * This is the one deliberate global in the application. There is no DI
 * container, and threading a connection through every resource model's
 * constructor buys nothing when there is exactly one connection for the
 * lifetime of a request.
 *
 * EMULATE_PREPARES is off: real prepared statements, so bound values are never
 * interpolated into SQL text. Column and table identifiers can never be bound,
 * which is why AbstractResource keeps an explicit $fields whitelist.
 */
final class Db
{
    private static ?PDO $connection = null;

    public static function instance(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host    = (string) Config::env('db.host', 'localhost');
        $port    = (int) Config::env('db.port', 3306);
        $name    = (string) Config::env('db.name', '');
        $charset = (string) Config::env('db.charset', 'utf8mb4');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

        try {
            self::$connection = new PDO(
                $dsn,
                (string) Config::env('db.user', ''),
                (string) Config::env('db.password', ''),
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_STRINGIFY_FETCHES  => false,
                ]
            );
        } catch (PDOException $e) {
            // The DSN carries no password, but the message may name the user
            // and host -- fine for a log, never for a browser.
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }

        return self::$connection;
    }

    /**
     * Connect without selecting a database. Used by the CLI for db:reset,
     * which must be able to drop and recreate the schema itself.
     */
    public static function serverConnection(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=%s',
            (string) Config::env('db.host', 'localhost'),
            (int) Config::env('db.port', 3306),
            (string) Config::env('db.charset', 'utf8mb4')
        );

        return new PDO(
            $dsn,
            (string) Config::env('db.user', ''),
            (string) Config::env('db.password', ''),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
        );
    }

    public static function reset(): void
    {
        self::$connection = null;
    }

    /** Quote an identifier that has already been whitelist-checked. */
    public static function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
