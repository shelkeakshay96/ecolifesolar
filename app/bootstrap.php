<?php

/**
 * Application bootstrap. The single entry point for both HTTP (pub/index.php)
 * and CLI (bin/ecolife).
 *
 * Order matters and is fixed:
 *   constants -> autoload -> config -> error handler -> modules
 *
 * Nothing before the error handler is allowed to fail in a way worth catching,
 * which is why config loading is kept to reading two PHP arrays.
 */

declare(strict_types=1);

define('BP', dirname(__DIR__));
define('ECOLIFE_START', microtime(true));

require BP . '/app/autoload.php';

date_default_timezone_set('Asia/Kolkata');
mb_internal_encoding('UTF-8');
setlocale(LC_ALL, 'en_IN.UTF-8', 'en_US.UTF-8', 'C');

\EcoLife\Core\Model\Config::init(BP . '/app/etc');
\EcoLife\Core\App\ErrorHandler::register();
\EcoLife\Core\Module\ModuleList::load();
