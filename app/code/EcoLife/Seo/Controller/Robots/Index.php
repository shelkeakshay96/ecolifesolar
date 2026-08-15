<?php

declare(strict_types=1);

namespace EcoLife\Seo\Controller\Robots;

use EcoLife\Core\Controller\AbstractAction;
use EcoLife\Core\Controller\ResultInterface;
use EcoLife\Core\Model\Config;

/**
 * /robots.txt
 *
 * Deliberately permissive. The instinct with a robots file is to start
 * disallowing things, and on a twelve-page brochure site every Disallow is a
 * chance to accidentally delist the pages you are trying to rank -- a stray
 * "Disallow: /s" would take out /services and all four service-area pages at
 * once. So: allow everything, name the handful of paths that genuinely should
 * not be crawled, and point at the sitemap.
 *
 * Generated rather than static so the Sitemap line carries the host the request
 * actually arrived on. That line must be an absolute URL, and a file committed
 * to the repository cannot know whether it is being served from
 * ecolifegrp.com or from a staging domain.
 */
final class Index extends AbstractAction
{
    protected function execute(): ResultInterface
    {
        $base  = rtrim($this->context->getUrl()->getBaseUrl(), '/');
        $admin = Config::adminFrontName();

        $lines = [
            'User-agent: *',
            'Allow: /',
            '',
            '# The admin panel. Also noindex on every page it serves, because',
            '# robots.txt governs crawling and not indexing -- a URL that is',
            '# linked to from anywhere can be listed without ever being fetched.',
            'Disallow: /' . $admin . '/',
            '',
            '# Form and AJAX endpoints. Nothing here answers a GET usefully.',
            'Disallow: /lead/',
            'Disallow: /calculator/estimate',
            '',
            'Sitemap: ' . $base . '/sitemap.xml',
            '',
        ];

        return $this->resultRaw()
            ->setContentType('text/plain; charset=UTF-8')
            ->setContents(implode("\n", $lines));
    }
}
