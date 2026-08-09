<?php

declare(strict_types=1);

namespace EcoLife\Core\App\Router;

use EcoLife\Core\App\Context;
use EcoLife\Core\Controller\AbstractAction;

/**
 * A router either recognises the path and returns an action, or returns null
 * and lets the next router try. Returning null from all of them is a 404.
 */
interface RouterInterface
{
    public function match(Context $context): ?AbstractAction;

    /** Lower runs first. Cms\App\Router\Page (10) before Standard (100). */
    public function getSortOrder(): int;
}
