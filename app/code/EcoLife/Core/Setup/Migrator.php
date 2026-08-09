<?php

declare(strict_types=1);

namespace EcoLife\Core\Setup;

use EcoLife\Core\Model\Db;
use EcoLife\Core\Module\ModuleList;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Applies per-module, numbered, append-only SQL migrations.
 *
 * Idempotency comes from the setup_migration ledger, not from IF NOT EXISTS.
 * That distinction is the whole design: a migration that has been recorded is
 * skipped, and one that has not been recorded runs in full and fails loudly if
 * it cannot. A half-applied migration is therefore visible, where IF NOT EXISTS
 * would have quietly hidden it.
 *
 * Migrations run in module load order, so a foreign key can always point at a
 * table an earlier module created.
 */
final class Migrator
{
    private const LEDGER = 'setup_migration';

    /** @var list<string> */
    private array $log = [];

    public function upgrade(bool $dryRun = false): array
    {
        $this->log = [];
        $pdo       = Db::instance();
        $applied   = $this->appliedMigrations($pdo);
        $count     = 0;

        foreach ($this->pending($applied) as $migration) {
            ['module' => $module, 'name' => $name, 'file' => $file] = $migration;

            if ($dryRun) {
                $this->log[] = sprintf('  would apply  %-18s %s', $module, $name);
                $count++;
                continue;
            }

            $this->apply($pdo, $module, $name, $file);
            $this->log[] = sprintf('  applied      %-18s %s', $module, $name);
            $count++;

            // The ledger's own migration is special: until it has run there is
            // nowhere to record anything, so re-read the applied list once it
            // exists rather than assuming it stayed empty.
            if ($module === 'EcoLife_Core' && str_contains($name, 'setup-migration')) {
                $applied = $this->appliedMigrations($pdo);
            }
        }

        if ($count === 0) {
            $this->log[] = '  nothing to do -- database is current';
        }

        return $this->log;
    }

    /**
     * @param array<string, true> $applied "module::name" keys
     * @return list<array{module: string, name: string, file: string}>
     */
    private function pending(array $applied): array
    {
        $pending = [];

        foreach (ModuleList::names() as $module) {
            $dir = ModuleList::path($module) . '/Setup/migrations';
            if (!is_dir($dir)) {
                continue;
            }

            $files = glob($dir . '/*.sql') ?: [];
            sort($files, SORT_NATURAL);

            foreach ($files as $file) {
                $name = basename($file);
                if (isset($applied[$module . '::' . $name])) {
                    continue;
                }
                $pending[] = ['module' => $module, 'name' => $name, 'file' => $file];
            }
        }

        return $pending;
    }

    private function apply(PDO $pdo, string $module, string $name, string $file): void
    {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException("Cannot read migration {$file}");
        }

        foreach (self::statements($sql) as $index => $statement) {
            try {
                $pdo->exec($statement);
            } catch (Throwable $e) {
                throw new RuntimeException(sprintf(
                    "Migration %s::%s failed on statement %d:\n%s\n\n%s",
                    $module,
                    $name,
                    $index + 1,
                    self::excerpt($statement),
                    $e->getMessage()
                ), 0, $e);
            }
        }

        $this->record($pdo, $module, $name);
    }

    private function record(PDO $pdo, string $module, string $name): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO ' . Db::quoteIdentifier(self::LEDGER) . ' (module, migration) VALUES (?, ?)'
        );
        $stmt->execute([$module, $name]);
    }

    /** @return array<string, true> */
    private function appliedMigrations(PDO $pdo): array
    {
        if (!$this->ledgerExists($pdo)) {
            return [];
        }

        $applied = [];
        $rows = $pdo->query('SELECT module, migration FROM ' . Db::quoteIdentifier(self::LEDGER));
        foreach ($rows ?: [] as $row) {
            $applied[$row['module'] . '::' . $row['migration']] = true;
        }
        return $applied;
    }

    private function ledgerExists(PDO $pdo): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute([self::LEDGER]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Split a migration file into statements.
     *
     * Line comments are stripped and semicolons inside quoted strings are
     * ignored, so a seeded value containing a semicolon does not split the
     * statement in half. Anything more elaborate than this would be a SQL
     * parser, and these files are written by hand for one engine.
     *
     * @return list<string>
     */
    public static function statements(string $sql): array
    {
        $statements = [];
        $current    = '';
        $quote      = null;
        $length     = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($quote === null && $char === '-' && $next === '-') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }
                $current .= "\n";
                continue;
            }

            if ($quote !== null) {
                $current .= $char;
                if ($char === '\\') {                 // escaped char inside a string
                    $current .= $next;
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $current .= $char;
                continue;
            }

            if ($char === ';') {
                if (trim($current) !== '') {
                    $statements[] = trim($current);
                }
                $current = '';
                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $statements[] = trim($current);
        }

        return $statements;
    }

    private static function excerpt(string $statement): string
    {
        $lines = array_slice(explode("\n", $statement), 0, 4);
        return implode("\n", $lines) . (substr_count($statement, "\n") > 4 ? "\n  ..." : '');
    }

    /** @return list<array{module: string, name: string, applied: bool, applied_at: ?string}> */
    public function status(): array
    {
        $pdo     = Db::instance();
        $applied = [];

        if ($this->ledgerExists($pdo)) {
            $rows = $pdo->query('SELECT module, migration, applied_at FROM ' . Db::quoteIdentifier(self::LEDGER));
            foreach ($rows ?: [] as $row) {
                $applied[$row['module'] . '::' . $row['migration']] = $row['applied_at'];
            }
        }

        $status = [];
        foreach (ModuleList::names() as $module) {
            $dir = ModuleList::path($module) . '/Setup/migrations';
            $files = is_dir($dir) ? (glob($dir . '/*.sql') ?: []) : [];
            sort($files, SORT_NATURAL);

            foreach ($files as $file) {
                $name = basename($file);
                $key  = $module . '::' . $name;
                $status[] = [
                    'module'     => $module,
                    'name'       => $name,
                    'applied'    => isset($applied[$key]),
                    'applied_at' => $applied[$key] ?? null,
                ];
            }
        }

        return $status;
    }
}
