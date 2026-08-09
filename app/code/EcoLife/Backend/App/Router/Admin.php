<?php

declare(strict_types=1);

namespace EcoLife\Backend\App\Router;

use EcoLife\Core\App\Context;
use EcoLife\Core\App\Router\RouterInterface;
use EcoLife\Core\Controller\AbstractAction;

/**
 * Short admin URLs that the standard router cannot express.
 *
 * The standard scheme derives the controller directory from the second path
 * segment, so /admin/settings parses as frontName "settings", controller
 * "index" -- which collides with the dashboard, because both would resolve to
 * Adminhtml\Index\Index.
 *
 * Rather than accept /admin/settings/settings, this router holds a small
 * explicit map for the handful of admin pages that deserve a one-word URL.
 * It is a map, not a mechanism: if it grows past a screenful, the right answer
 * is a separate module with its own front name, not more entries here.
 */
final class Admin implements RouterInterface
{
    private const MAP = [
        ''         => \EcoLife\Backend\Controller\Adminhtml\Index\Index::class,
        'settings' => \EcoLife\Backend\Controller\Adminhtml\Settings\Index::class,
    ];

    public function getSortOrder(): int
    {
        return 20;
    }

    public function match(Context $context): ?AbstractAction
    {
        if (!$context->getArea()->isAdmin()) {
            return null;
        }

        $segments = $context->getRequest()->getSegments();

        if (count($segments) > 1) {
            return null;
        }

        $class = self::MAP[$segments[0] ?? ''] ?? null;

        return $class === null ? null : new $class($context);
    }
}
