<?php

declare(strict_types=1);

namespace EcoLife\Core\App;

use EcoLife\Core\Model\Config;

/**
 * Thin wrapper over PHP's session, with the cookie flags set correctly and
 * storage pointed at var/session/ so sessions are not shared with whatever else
 * happens to run on the machine.
 *
 * Namespacing by area matters: logging into the admin panel must not grant
 * anything on the frontend, and a frontend form key must not validate an admin
 * POST. Both areas share one PHP session, but their data never collides.
 */
final class Session
{
    private static bool $started = false;

    public function __construct(private readonly Area $area)
    {
    }

    public function start(): void
    {
        if (self::$started || PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $path = BP . '/var/session';
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
        if (is_writable($path)) {
            session_save_path($path);
        }

        session_name('ECOLIFE_SESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => !Config::isDeveloperMode(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
        self::$started = true;
    }

    private function &bucket(): array
    {
        $this->start();
        $key = $this->area->getCode();
        if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }
        return $_SESSION[$key];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $bucket = &$this->bucket();
        return $bucket[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $bucket = &$this->bucket();
        $bucket[$key] = $value;
    }

    public function has(string $key): bool
    {
        $bucket = &$this->bucket();
        return array_key_exists($key, $bucket);
    }

    public function unset(string $key): void
    {
        $bucket = &$this->bucket();
        unset($bucket[$key]);
    }

    /** Read once and remove -- for flash messages and one-shot form data. */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->unset($key);
        return $value;
    }

    public function regenerateId(): void
    {
        $this->start();
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION[$this->area->getCode()] = [];
    }
}
