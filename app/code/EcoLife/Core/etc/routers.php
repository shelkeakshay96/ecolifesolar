<?php

/**
 * Routers contributed by EcoLife_Core.
 *
 * Each module may declare its own; FrontController merges them and sorts by
 * getSortOrder(). This indirection is what lets Core dispatch through
 * Cms\App\Router\Page without ever naming it.
 */

declare(strict_types=1);

return [
    \EcoLife\Core\App\Router\Standard::class,
];
