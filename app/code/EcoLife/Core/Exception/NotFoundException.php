<?php

declare(strict_types=1);

namespace EcoLife\Core\Exception;

use RuntimeException;

/**
 * No router matched, or a controller decided the thing being asked for does not
 * exist. Http::run() turns this into a 404 rendered through the normal page
 * pipeline, which is why /admin/nonsense gets admin chrome without anyone
 * writing a line of code for it.
 */
final class NotFoundException extends RuntimeException
{
}
