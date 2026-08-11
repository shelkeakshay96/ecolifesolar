<?php

declare(strict_types=1);

namespace EcoLife\Core\Console;

use EcoLife\Core\App\Area;
use EcoLife\Core\App\Router\Standard;
use EcoLife\Core\Model\Config;
use EcoLife\Core\Model\Db;
use EcoLife\Core\Module\ModuleList;
use EcoLife\Core\Setup\Migrator;
use PDO;
use Throwable;

/**
 * The bin/ecolife command line.
 *
 * A flat dispatcher rather than a command framework: ten commands, each a
 * method, no registry and no argument parser beyond what is actually used.
 */
final class Cli
{
    private const STYLESHEET = BP . '/pub/css/ecolife.css';

    private const COMMANDS = [
        'serve'             => 'Run the development server on pub/ (--port, --host, --watch)',
        'module:status'     => 'List modules in load order, with their migrations',
        'setup:upgrade'     => 'Apply pending migrations (--dry-run to preview)',
        'db:reset'          => 'Drop and recreate the schema, then upgrade (--force to skip the prompt)',
        'db:seed'           => 'Insert sample leads and gallery items for local development',
        'admin:user:create' => 'Create an admin user',
        'route:list'        => 'Show every route in both areas',
        'assets:build'      => 'Compile Tailwind into pub/css/ecolife.css (--watch for development)',
        'lint:templates'    => 'Fail on any unescaped output in a .phtml template',
        'mail:test'         => 'Send a test message through the configured transport',
    ];

    /** @param list<string> $argv */
    public static function run(array $argv): int
    {
        $command = $argv[1] ?? 'help';
        $args    = array_slice($argv, 2);

        $cli = new self();

        try {
            return match ($command) {
                'serve'             => $cli->serve($args),
                'module:status'     => $cli->moduleStatus(),
                'setup:upgrade'     => $cli->setupUpgrade(in_array('--dry-run', $args, true)),
                'db:reset'          => $cli->dbReset(in_array('--force', $args, true)),
                'db:seed'           => $cli->dbSeed(),
                'admin:user:create' => $cli->adminUserCreate($args),
                'route:list'        => $cli->routeList(),
                'assets:build'      => $cli->assetsBuild(in_array('--watch', $args, true)),
                'lint:templates'    => $cli->lintTemplates(),
                'mail:test'         => $cli->mailTest($args),
                'help', '--help', '-h' => $cli->help(),
                default             => $cli->unknown($command),
            };
        } catch (Throwable $e) {
            self::error($e->getMessage());
            if (Config::isDeveloperMode()) {
                fwrite(STDERR, $e->getTraceAsString() . PHP_EOL);
            }
            return 1;
        }
    }

    // ---------------------------------------------------------------- commands

    private function moduleStatus(): int
    {
        $migrator = new Migrator();
        $status   = [];
        foreach ($migrator->status() as $row) {
            $status[$row['module']][] = $row;
        }

        self::heading('Modules in load order');

        foreach (ModuleList::all() as $name => $module) {
            printf(
                "  %-20s v%-8s %s\n",
                $name,
                $module['setup_version'],
                $module['sequence'] ? 'after ' . implode(', ', $module['sequence']) : ''
            );

            foreach ($status[$name] ?? [] as $migration) {
                printf(
                    "      %s %s%s\n",
                    $migration['applied'] ? '[x]' : '[ ]',
                    $migration['name'],
                    $migration['applied_at'] ? '  ' . $migration['applied_at'] : ''
                );
            }
        }

        $disabled = array_diff(array_keys(Config::modules()), ModuleList::names());
        if ($disabled) {
            echo "\n  declared but not loaded: " . implode(', ', $disabled) . "\n";
            echo "  (either switched off in app/etc/config.php, or not yet built)\n";
        }

        return 0;
    }

    private function setupUpgrade(bool $dryRun): int
    {
        self::heading($dryRun ? 'Pending migrations (dry run)' : 'Applying migrations');

        foreach ((new Migrator())->upgrade($dryRun) as $line) {
            echo $line . "\n";
        }

        return 0;
    }

    private function dbReset(bool $force): int
    {
        $name = (string) Config::env('db.name');

        if (!$force) {
            self::warn("This DROPS the entire `{$name}` schema. Every lead in it is gone.");
            if (strtolower(trim(self::prompt('Type the database name to confirm: '))) !== strtolower($name)) {
                echo "Aborted.\n";
                return 1;
            }
        }

        $pdo = Db::serverConnection();
        $pdo->exec('DROP DATABASE IF EXISTS ' . Db::quoteIdentifier($name));
        $pdo->exec(
            'CREATE DATABASE ' . Db::quoteIdentifier($name)
            . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
        Db::reset();

        self::ok("Schema `{$name}` recreated.");

        return $this->setupUpgrade(false);
    }

    private function dbSeed(): int
    {
        $pdo = Db::instance();

        if ((int) $pdo->query('SELECT COUNT(*) FROM ' . Db::quoteIdentifier('lead'))->fetchColumn() > 0) {
            self::warn('lead already has rows -- seeding skipped so real enquiries are never touched.');
            return 0;
        }

        $leads = [
            ['residential', 'Sunita Deshmukh',  'sunita.d@example.com',   '9822011223', 'Satara', '415001', '2500-5000',  3200.00, 'rcc',         650,  'new',        'home'],
            ['residential', 'Rahul Patil',      null,                     '9730044556', 'Karad',  '415110', '5000-10000', 7400.00, 'metal_sheet', 1200, 'contacted',  'contact'],
            ['society',     'Anil Kulkarni',    'anil.k@example.com',     '9881077889', 'Satara', '415002', '10000+',     42000.00,'rcc',         5400, 'new',        'contact'],
            ['commercial',  'Meera Industries', 'accounts@example.com',   '9028099001', 'Satara', '415004', '10000+',     88000.00,'metal_sheet', 9000, 'survey_scheduled', 'home'],
        ];

        $stmt = $pdo->prepare(
            'INSERT INTO ' . Db::quoteIdentifier('lead') . '
             (lead_type, name, email, phone, city, pincode, monthly_bill_range, monthly_bill_amount,
              roof_type, roof_area_sqft, status, source_page, ip_address, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, NOW() - INTERVAL ? DAY)'
        );

        foreach ($leads as $i => $lead) {
            $stmt->execute([...$lead, '127.0.0.1', $i * 3]);
        }
        self::ok(count($leads) . ' sample leads inserted.');

        if ((int) $pdo->query('SELECT COUNT(*) FROM gallery_item')->fetchColumn() === 0) {
            $gallery = $pdo->prepare(
                'INSERT INTO gallery_item (title, description, image_path, location, system_size_kw, category, sort_order)
                 VALUES (?,?,?,?,?,?,?)'
            );
            $items = [
                ['3 kW rooftop, Satara',      'Residential RCC rooftop installation.', 'gallery/sample-1.jpg', 'Satara, Maharashtra',  3.00,  'residential', 10],
                ['48 kW society rooftop',     'Housing society common-area supply.',   'gallery/sample-2.jpg', 'Karad, Maharashtra',  48.00,  'society',     20],
                ['15 kW commercial shed',     'Metal sheet roof, industrial unit.',    'gallery/sample-3.jpg', 'Satara, Maharashtra', 15.00,  'commercial',  30],
            ];
            foreach ($items as $item) {
                $gallery->execute($item);
            }
            self::ok(count($items) . ' sample gallery items inserted (image files not included).');
        }

        return 0;
    }

    /** @param list<string> $args */
    private function adminUserCreate(array $args): int
    {
        $options  = self::options($args);
        $username = $options['username'] ?? self::prompt('Username: ');
        $email    = $options['email']    ?? self::prompt('Email: ');
        $role     = $options['role']     ?? 'admin';

        $password = $options['password'] ?? self::promptHidden('Password (min 12 chars): ');
        if (!isset($options['password'])) {
            if ($password !== self::promptHidden('Confirm password: ')) {
                self::error('Passwords do not match.');
                return 1;
            }
        }

        $errors = [];
        if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
            $errors[] = 'Username must be 3-50 characters of letters, digits, dot, underscore or hyphen.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email is not valid.';
        }
        if (strlen($password) < 12) {
            $errors[] = 'Password must be at least 12 characters.';
        }
        if (!in_array($role, ['admin', 'staff'], true)) {
            $errors[] = "Role must be 'admin' or 'staff'.";
        }
        if ($errors) {
            foreach ($errors as $error) {
                self::error($error);
            }
            return 1;
        }

        $pdo  = Db::instance();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM admin_user WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ((int) $stmt->fetchColumn() > 0) {
            self::error('That username or email already exists.');
            return 1;
        }

        $insert = $pdo->prepare(
            'INSERT INTO admin_user (username, email, password_hash, role, is_active)
             VALUES (?, ?, ?, ?, 1)'
        );
        $insert->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $role]);

        self::ok("Admin user '{$username}' created (role: {$role}).");
        echo "  Sign in at /" . Config::adminFrontName() . "\n";

        return 0;
    }

    private function routeList(): int
    {
        foreach ([Area::FRONTEND, Area::ADMINHTML] as $area) {
            self::heading(ucfirst($area) . ' routes');

            $routes = Standard::routes($area);
            if ($routes === []) {
                echo "  (none)\n";
                continue;
            }

            $prefix = $area === Area::ADMINHTML ? '/' . Config::adminFrontName() : '';

            foreach ($routes as $frontName => $module) {
                printf("  %-28s %s\n", $prefix . '/' . $frontName, $module);

                foreach ($this->controllersFor($module, $area) as $path => $class) {
                    printf("      %-24s %s\n", $prefix . '/' . $frontName . $path, $class);
                }
            }
        }

        self::heading('Routers');
        foreach (\EcoLife\Core\App\FrontController::routers() as $router) {
            printf("  %-4d %s\n", $router->getSortOrder(), $router::class);
        }

        return 0;
    }

    /** @return array<string, string> url tail => class */
    private function controllersFor(string $module, string $area): array
    {
        $base = ModuleList::path($module) . '/Controller' . ($area === Area::ADMINHTML ? '/Adminhtml' : '');
        if (!is_dir($base)) {
            return [];
        }

        $found = [];
        foreach (glob($base . '/*/*.php') ?: [] as $file) {
            $action     = basename($file, '.php');
            $controller = basename(dirname($file));

            // Skip the frontend tree when listing adminhtml controllers.
            if ($area === Area::FRONTEND && $controller === 'Adminhtml') {
                continue;
            }

            $class = sprintf(
                '%s\\Controller\\%s%s\\%s',
                str_replace('_', '\\', $module),
                $area === Area::ADMINHTML ? 'Adminhtml\\' : '',
                $controller,
                $action
            );

            // Controller/ also holds Result/ and the base classes. Only things
            // the router could actually dispatch belong in this listing.
            if (!class_exists($class) || !is_subclass_of($class, \EcoLife\Core\Controller\AbstractAction::class)) {
                continue;
            }

            $tail = '/' . self::snake($controller) . '/' . self::snake($action);
            $tail = str_replace('/index/index', '', $tail);
            $tail = preg_replace('#/index$#', '', $tail) ?? $tail;

            $found[$tail] = $class;
        }

        ksort($found);
        return $found;
    }

    private function assetsBuild(bool $watch): int
    {
        $command = $this->tailwindCommand($watch);
        if ($command === null) {
            return 1;
        }

        passthru($command, $exitCode);

        if (!$watch && $exitCode === 0) {
            $size = @filesize(self::STYLESHEET);
            self::ok('pub/css/ecolife.css written' . ($size ? ' (' . number_format($size / 1024, 1) . ' KB)' : ''));
        }

        return $exitCode;
    }

    /**
     * The one place that knows how to invoke Tailwind. `serve --watch` and
     * `assets:build` share it so the flags cannot drift apart.
     *
     * Returns null, having already reported why, when the binary is missing.
     */
    private function tailwindCommand(bool $watch): ?string
    {
        $binary = BP . '/tools/tailwindcss';
        if (!is_executable($binary)) {
            self::error('tools/tailwindcss is missing. Run: bash bin/tailwind-install.sh');
            return null;
        }

        return sprintf(
            '%s -i %s -o %s %s',
            escapeshellarg($binary),
            escapeshellarg(BP . '/app/code/EcoLife/Theme/view/base/web/css/input.css'),
            escapeshellarg(self::STYLESHEET),
            // =always, not plain --watch: Tailwind stops watching when stdin
            // closes, so a backgrounded `bin/ecolife assets:build --watch`
            // would do one build and then sit there looking healthy.
            $watch ? '--watch=always' : '--minify'
        );
    }

    /**
     * Runs the site locally.
     *
     * The document root is pinned to pub/ and derived from BP, not from
     * wherever the command was invoked. That is the entire reason this command
     * exists rather than a line in a README: `php -S` from the project root
     * would happily serve app/etc/env.php, and env.php holds the database
     * password.
     *
     * @param list<string> $args
     */
    private function serve(array $args): int
    {
        $host  = self::option($args, '--host', '0.0.0.0');
        $port  = (int) self::option($args, '--port', '8080');
        $watch = in_array('--watch', $args, true);
        $root  = BP . '/pub';

        if ($port < 1 || $port > 65535) {
            self::error('--port must be between 1 and 65535.');
            return 1;
        }

        // A host reaches the shell below, so it is restricted to the shapes a
        // bind address can actually take rather than merely escaped.
        if (!preg_match('/^(\[[0-9a-f:]+\]|[0-9a-z.\-]+)$/i', $host)) {
            self::error('--host must be a hostname, an IPv4 address, or a bracketed IPv6 address.');
            return 1;
        }

        self::heading('Development server');

        // ---- preflight. Fail here with a sentence, rather than in the
        //      bootstrap of whatever page the visitor asked for first.

        try {
            Db::instance();
            self::ok('MySQL reachable');
        } catch (Throwable $e) {
            self::error('MySQL is not reachable: ' . $e->getMessage());
            self::warn('Start it with: sudo service mysql start');
            return 1;
        }

        if (!is_file(self::STYLESHEET)) {
            if (!$watch) {
                self::error('pub/css/ecolife.css has not been built.');
                self::warn('Run: bin/ecolife assets:build   (or add --watch here)');
                return 1;
            }
            self::warn('pub/css/ecolife.css missing — the watcher will build it now.');
        } else {
            self::ok('Stylesheet present');
        }

        if (self::portInUse($port)) {
            self::error("Port {$port} is already in use.");
            self::warn("Stop the process holding it, or pass --port=" . ($port + 1));
            return 1;
        }

        // ---- optional stylesheet watcher, alongside the server

        $tailwind = null;
        if ($watch) {
            $command = $this->tailwindCommand(true);
            if ($command === null) {
                return 1;
            }

            // Inherit stdout and stderr so rebuild lines appear in the same
            // terminal. The child shares this process group, so the Ctrl-C
            // that stops the server stops the watcher too; the shutdown
            // function covers every other way out.
            $tailwind = proc_open($command, [1 => STDOUT, 2 => STDERR], $pipes);

            if (is_resource($tailwind)) {
                self::ok('Tailwind watching for template changes');
                register_shutdown_function(static function () use ($tailwind) {
                    if (is_resource($tailwind)) {
                        proc_terminate($tailwind);
                        proc_close($tailwind);
                    }
                });
            } else {
                self::warn('Could not start the Tailwind watcher; serving the stylesheet as built.');
            }
        }

        // ---- addresses

        echo "\n";
        self::ok('Document root  ' . $root);
        self::ok('Local          http://localhost:' . $port);

        $lan = self::lanAddress();
        if ($lan !== null) {
            // Under WSL2 this is also the address to use from Windows if
            // localhost forwarding is turned off, and the only one that works
            // from a phone on the same network.
            self::ok('Network        http://' . $lan . ':' . $port);
        }

        self::ok('Admin          http://localhost:' . $port . '/admin');
        echo "\n  Ctrl-C to stop.\n\n";

        passthru(sprintf(
            '%s -S %s -t %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($host . ':' . $port),
            escapeshellarg($root)
        ), $exitCode);

        return $exitCode;
    }

    /** Reads `--name=value` out of the argument list. */
    private static function option(array $args, string $name, string $default): string
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, $name . '=')) {
                return substr($arg, strlen($name) + 1);
            }
        }

        return $default;
    }

    /** True when something already answers on the loopback interface. */
    private static function portInUse(int $port): bool
    {
        $socket = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $error, 0.3);
        if ($socket === false) {
            return false;
        }

        fclose($socket);
        return true;
    }

    /** The first non-loopback address of this host, if there is one. */
    private static function lanAddress(): ?string
    {
        $output = @shell_exec('hostname -I 2>/dev/null');
        $first  = strtok(trim((string) $output), ' ');

        return ($first === false || $first === '' || str_starts_with($first, '127.')) ? null : $first;
    }

    /**
     * Every `<?=` in a template must be followed by an escaper or by something
     * that has already produced escaped HTML. This is the mechanical half of
     * the XSS defence -- code review is the other half, and it is the half that
     * gets tired.
     */
    private function lintTemplates(): int
    {
        // getContentHtml is on this list for the same reason getChildHtml is:
        // it returns HTML a block already rendered and escaped. Everything else
        // must pass through an escaper, including class-name ternaries -- the
        // rule is only useful if it has no "obviously safe" exceptions.
        $allowed = [
            'escapeHtml', 'escapeHtmlAttr', 'escapeUrl', 'escapeJs',
            'getChildHtml', 'renderChild', 'getContentHtml', 'toHtml',
        ];
        $findings = [];
        $checked  = 0;

        foreach (ModuleList::names() as $module) {
            $dir = ModuleList::path($module) . '/view';
            if (!is_dir($dir)) {
                continue;
            }

            foreach (self::phtmlFiles($dir) as $file) {
                $checked++;
                foreach (file($file) ?: [] as $number => $line) {
                    if (!str_contains($line, '<?=')) {
                        continue;
                    }
                    foreach (self::echoExpressions($line) as $expression) {
                        foreach ($allowed as $safe) {
                            if (str_contains($expression, $safe)) {
                                continue 2;
                            }
                        }
                        $findings[] = sprintf(
                            '  %s:%d  %s',
                            str_replace(BP . '/', '', $file),
                            $number + 1,
                            trim($expression)
                        );
                    }
                }
            }
        }

        self::heading('Template output escaping');
        echo "  {$checked} template(s) checked\n";

        if ($findings === []) {
            self::ok('no unescaped output found');
            return 0;
        }

        echo "\n";
        foreach ($findings as $finding) {
            echo $finding . "\n";
        }
        self::error(count($findings) . ' unescaped output expression(s)');

        return 1;
    }

    /**
     * Proves the transport works without waiting for a customer to fill in the
     * form. Referenced by name in docs/vendored-libs.md.
     *
     * @param list<string> $args
     */
    private function mailTest(array $args): int
    {
        $transport = '\\EcoLife\\Mail\\Model\\Transport';

        if (!class_exists($transport)) {
            self::error('EcoLife_Mail is not enabled.');
            return 1;
        }

        $recipient = $args[0] ?? '';

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            self::error('Usage: bin/ecolife mail:test you@example.com');
            return 1;
        }

        $mode = (string) Config::env('mail.transport', 'file');
        self::heading("Sending a test message via the '{$mode}' transport");

        $sent = (new $transport())->send(
            [$recipient],
            'EcoLifeSolar test message',
            '<p>If you are reading this, the mail transport works.</p>'
            . '<p>Sent ' . date('d M Y H:i') . ' from ' . gethostname() . '.</p>'
        );

        if (!$sent) {
            self::error('Transport reported failure. See var/log/exception.log.');
            return 1;
        }

        self::ok($mode === 'file'
            ? 'Written to var/log/mail/ -- nothing left this machine.'
            : "Handed to the SMTP relay for {$recipient}.");

        return 0;
    }

    // ----------------------------------------------------------------- helpers

    /** @return list<string> */
    private static function echoExpressions(string $line): array
    {
        preg_match_all('/<\?=(.*?)(\?>|$)/s', $line, $matches);
        return $matches[1] ?? [];
    }

    /** @return list<string> */
    private static function phtmlFiles(string $dir): array
    {
        $files    = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'phtml') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);
        return $files;
    }

    private static function snake(string $studly): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $studly));
    }

    /**
     * --key=value and --flag, in a form small enough to read.
     *
     * @param list<string> $args
     * @return array<string, string>
     */
    private static function options(array $args): array
    {
        $options = [];
        foreach ($args as $arg) {
            if (!str_starts_with($arg, '--')) {
                continue;
            }
            $pair = explode('=', substr($arg, 2), 2);
            $options[$pair[0]] = $pair[1] ?? '1';
        }
        return $options;
    }

    private static function prompt(string $label): string
    {
        echo $label;
        return trim((string) fgets(STDIN));
    }

    private static function promptHidden(string $label): string
    {
        echo $label;
        // Falls back to visible input where stty is unavailable, which is
        // better than refusing to create an admin user at all.
        $hasStty = self::hasStty();
        if ($hasStty) {
            shell_exec('stty -echo 2>/dev/null');
        }
        $value = trim((string) fgets(STDIN));
        if ($hasStty) {
            shell_exec('stty echo 2>/dev/null');
            echo "\n";
        }
        return $value;
    }

    private static function hasStty(): bool
    {
        return function_exists('shell_exec') && trim((string) shell_exec('command -v stty 2>/dev/null')) !== '';
    }

    private function help(): int
    {
        echo "EcoLifeSolar CLI\n\n  Usage: bin/ecolife <command> [options]\n\n";
        foreach (self::COMMANDS as $name => $description) {
            printf("  %-20s %s\n", $name, $description);
        }
        echo "\n";
        return 0;
    }

    private function unknown(string $command): int
    {
        self::error("Unknown command '{$command}'");
        $this->help();
        return 1;
    }

    private static function heading(string $text): void
    {
        echo "\n" . $text . "\n" . str_repeat('-', strlen($text)) . "\n";
    }

    private static function ok(string $text): void
    {
        echo '  ' . $text . "\n";
    }

    private static function warn(string $text): void
    {
        echo '  ! ' . $text . "\n";
    }

    private static function error(string $text): void
    {
        fwrite(STDERR, '  Error: ' . $text . PHP_EOL);
    }
}
