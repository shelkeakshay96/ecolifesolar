<?php

/**
 * PSR-4 autoloader.
 *
 * Plain PSR-4 lands exactly on the Magento directory layout for free: the
 * namespace EcoLife\Lead\Model\Lead resolves to
 * app/code/EcoLife/Lead/Model/Lead.php with no map file and no build step.
 *
 * PHPMailer is vendored under lib/internal rather than installed by Composer,
 * so it gets a prefix here too. See docs/vendored-libs.md.
 */

declare(strict_types=1);

$prefixes = [
    'EcoLife\\'              => BP . '/app/code/EcoLife/',
    'PHPMailer\\PHPMailer\\' => BP . '/lib/internal/PHPMailer/src/',
];

spl_autoload_register(static function (string $class) use ($prefixes): void {
    foreach ($prefixes as $prefix => $dir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }
        $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
        return;
    }
});
