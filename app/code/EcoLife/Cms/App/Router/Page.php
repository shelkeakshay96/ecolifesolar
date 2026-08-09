<?php

declare(strict_types=1);

namespace EcoLife\Cms\App\Router;

use EcoLife\Cms\Controller\Page\View;
use EcoLife\Core\App\Context;
use EcoLife\Core\App\Router\RouterInterface;
use EcoLife\Core\Controller\AbstractAction;

/**
 * Pretty single-segment URLs: / and /about rather than /cms/page/view/id/about.
 *
 * Runs before the standard router (sort order 10 against 100), so a CMS page
 * identifier wins over a module front name of the same word. That ordering is
 * intentional: content URLs are the ones customers see and share.
 *
 * Frontend only. The admin area has no CMS pages, and letting this router run
 * there would mean /admin/about resolved to a public template inside admin
 * chrome.
 */
final class Page implements RouterInterface
{
    public function getSortOrder(): int
    {
        return 10;
    }

    public function match(Context $context): ?AbstractAction
    {
        if ($context->getArea()->isAdmin()) {
            return null;
        }

        $segments = $context->getRequest()->getSegments();

        if (count($segments) > 1) {
            return null;
        }

        $identifier = $segments[0] ?? 'home';

        if (!isset(self::pages()[$identifier])) {
            return null;
        }

        $context->getRequest()->setParams(['identifier' => $identifier]);

        return new View($context);
    }

    /** @return array<string, array{title: string, description: string, template: string, body_class: string}> */
    public static function pages(): array
    {
        static $pages = null;
        return $pages ??= require dirname(__DIR__, 2) . '/etc/pages.php';
    }
}
