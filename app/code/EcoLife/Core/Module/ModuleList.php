<?php

declare(strict_types=1);

namespace EcoLife\Core\Module;

use EcoLife\Core\Model\Config;
use LogicException;

/**
 * Discovers, filters and orders the modules.
 *
 *   glob every registration.php  ->  require it (populates ModuleRegistry)
 *   drop anything switched off in app/etc/config.php
 *   parse each etc/module.xml for <sequence>
 *   depth-first topological sort, cycle detection, alphabetical tie-break
 *
 * <sequence> expresses load order only. There are no version constraints to
 * satisfy, so there is no resolver -- just an ordering.
 */
final class ModuleList
{
    /** @var list<string> in load order */
    private static array $ordered = [];

    /** @var array<string, array{path: string, setup_version: string, sequence: list<string>}> */
    private static array $modules = [];

    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        foreach (glob(BP . '/app/code/*/*/registration.php') ?: [] as $registration) {
            require_once $registration;
        }

        $enabled = Config::modules();

        foreach (ModuleRegistry::all() as $name => $path) {
            if (($enabled[$name] ?? 0) !== 1) {
                continue;
            }
            self::$modules[$name] = self::parseModuleXml($name, $path);
        }

        self::$ordered = self::sort();
        self::$loaded  = true;
    }

    /** @return array{path: string, setup_version: string, sequence: list<string>} */
    private static function parseModuleXml(string $name, string $path): array
    {
        $file = $path . '/etc/module.xml';
        if (!is_file($file)) {
            throw new LogicException("Module {$name} has no etc/module.xml");
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $xml    = simplexml_load_file($file);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            // Say what libxml objected to and where. "Unparseable" on its own
            // sends you hunting through a file that looks perfectly fine --
            // a double hyphen inside an XML comment, for instance, is invalid
            // and reads as ordinary prose.
            $detail = $errors === []
                ? 'no detail available'
                : trim($errors[0]->message) . ' on line ' . $errors[0]->line;

            throw new LogicException("Module {$name}: {$file} is not valid XML -- {$detail}");
        }

        $declared = (string) ($xml['name'] ?? '');
        if ($declared !== $name) {
            throw new LogicException(
                "Module {$name} declares itself as '{$declared}' in module.xml"
            );
        }

        $sequence = [];
        foreach ($xml->sequence->module ?? [] as $dependency) {
            $sequence[] = (string) $dependency['name'];
        }

        return [
            'path'          => $path,
            'setup_version' => (string) ($xml['setup_version'] ?? '1.0.0'),
            'sequence'      => $sequence,
        ];
    }

    /**
     * Depth-first topological sort. Alphabetical tie-break keeps the order
     * stable across machines, so `bin/ecolife module:status` is diffable.
     *
     * @return list<string>
     */
    private static function sort(): array
    {
        $sorted  = [];
        $state   = [];   // name => 'visiting' | 'done'
        $names   = array_keys(self::$modules);
        sort($names);

        $visit = static function (string $name, array $trail) use (&$visit, &$sorted, &$state): void {
            if (($state[$name] ?? null) === 'done') {
                return;
            }
            if (($state[$name] ?? null) === 'visiting') {
                $cycle = implode(' -> ', [...$trail, $name]);
                throw new LogicException("Circular module dependency: {$cycle}");
            }

            $state[$name] = 'visiting';

            $dependencies = self::$modules[$name]['sequence'];
            sort($dependencies);
            foreach ($dependencies as $dependency) {
                // A sequence entry pointing at a disabled or absent module is
                // ignored rather than fatal: config.php lists all eight Phase 1
                // modules, and the later ones do not exist on disk yet.
                if (isset(self::$modules[$dependency])) {
                    $visit($dependency, [...$trail, $name]);
                }
            }

            $state[$name] = 'done';
            $sorted[]     = $name;
        };

        foreach ($names as $name) {
            $visit($name, []);
        }

        return $sorted;
    }

    /** @return list<string> */
    public static function names(): array
    {
        return self::$ordered;
    }

    public static function has(string $name): bool
    {
        return isset(self::$modules[$name]);
    }

    public static function path(string $name): ?string
    {
        return self::$modules[$name]['path'] ?? null;
    }

    public static function setupVersion(string $name): ?string
    {
        return self::$modules[$name]['setup_version'] ?? null;
    }

    /** @return array<string, array{path: string, setup_version: string, sequence: list<string>}> */
    public static function all(): array
    {
        $all = [];
        foreach (self::$ordered as $name) {
            $all[$name] = self::$modules[$name];
        }
        return $all;
    }

    /**
     * Collect a per-area config file from every enabled module, in load order.
     *
     * @return list<string> absolute paths that exist
     */
    public static function configFiles(string $relative): array
    {
        $files = [];
        foreach (self::$ordered as $name) {
            $file = self::$modules[$name]['path'] . '/etc/' . ltrim($relative, '/');
            if (is_file($file)) {
                $files[] = $file;
            }
        }
        return $files;
    }
}
