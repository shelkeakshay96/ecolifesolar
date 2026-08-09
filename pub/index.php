<?php

/**
 * The only file in the webroot that PHP ever executes.
 *
 * app/, var/, lib/, bin/ and tools/ all sit outside pub/, so no deny rule is
 * load-bearing: application code is unreachable by URL because it is not under
 * the document root, not because a config file says so.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

(new \EcoLife\Core\App\Http())->run();
