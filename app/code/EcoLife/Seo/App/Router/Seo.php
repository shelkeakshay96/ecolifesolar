<?php

declare(strict_types=1);

namespace EcoLife\Seo\App\Router;

use EcoLife\Core\App\Context;
use EcoLife\Core\App\Router\RouterInterface;
use EcoLife\Core\Controller\AbstractAction;
use EcoLife\Seo\Controller\Robots\Index as RobotsAction;
use EcoLife\Seo\Controller\Sitemap\Index as SitemapAction;

/**
 * Two fixed filenames that crawlers request by convention: /robots.txt and
 * /sitemap.xml.
 *
 * Neither can be reached by any router already here. The CMS router matches
 * single segments against its whitelist, and "sitemap.xml" is not in it; the
 * standard router reads the first segment as a module front name and there is
 * no module called "sitemap.xml". So without this they are 404s, which is what
 * the live site currently returns for both.
 *
 * They could equally be static files in pub/ -- the .htaccess serves real files
 * before it reaches PHP. They are not, because a static sitemap is a sitemap
 * that goes stale the first time a page is added and nobody remembers, and a
 * static robots.txt cannot know which host it is being served from.
 *
 * Sort order 5, ahead of the CMS router at 10, because an exact filename match
 * should never be at the mercy of what somebody later adds to pages.php.
 */
final class Seo implements RouterInterface
{
    public function getSortOrder(): int
    {
        return 5;
    }

    public function match(Context $context): ?AbstractAction
    {
        if ($context->getArea()->isAdmin()) {
            return null;
        }

        $segments = $context->getRequest()->getSegments();

        if (count($segments) !== 1) {
            return null;
        }

        return match ($segments[0]) {
            'robots.txt'  => new RobotsAction($context),
            'sitemap.xml' => new SitemapAction($context),
            default       => null,
        };
    }
}
