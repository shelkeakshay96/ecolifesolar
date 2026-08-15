<?php

declare(strict_types=1);

namespace EcoLife\Seo\Model;

use EcoLife\Cms\App\Router\Page as PageRouter;
use EcoLife\Core\View\TemplateResolver;

/**
 * Every public URL worth submitting to a search engine.
 *
 * Built from the same whitelist the router serves pages from, so the sitemap
 * cannot drift out of step with the site: a page added to pages.php appears
 * here on the next request, and a page removed from it disappears. A
 * hand-written sitemap is wrong within a month and nobody notices, because
 * nothing on the site looks broken when it is.
 *
 * The two module routes -- /gallery and /calculator -- are listed explicitly,
 * because there is no equivalent whitelist to read them from. Their POST
 * endpoints (/lead/form, /calculator/estimate) are deliberately absent: they
 * answer nothing useful to a GET and have no business being crawled.
 */
final class UrlList
{
    /**
     * Priority is a hint, and a weak one -- crawlers largely ignore it. It is
     * set anyway because the alternative is every URL claiming the default 0.5,
     * which says the contact page matters as much as the home page.
     *
     * changefreq is omitted entirely. It is advisory to the point of being
     * decorative, and a page claiming "daily" that changes twice a year erodes
     * whatever trust the file has.
     */
    private const EXTRA = [
        '/gallery'    => 0.7,
        '/calculator' => 0.7,
    ];

    private const PRIORITY = [
        'home'     => 1.0,
        'services' => 0.9,
        'contact'  => 0.8,
        'about'    => 0.7,
    ];

    /** Service-area pages: the local-search reason this site exists. */
    private const AREA_PRIORITY = 0.9;

    /**
     * The resolver is injected rather than built here because it needs the
     * current Area, and a model that reaches for the request context to find
     * one is a model that cannot be called from the CLI.
     */
    public function __construct(private readonly TemplateResolver $templates)
    {
    }

    /**
     * @return list<array{loc: string, lastmod: string|null, priority: string}>
     */
    public function all(string $baseUrl): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $urls    = [];

        foreach (PageRouter::pages() as $identifier => $page) {
            $path = $identifier === 'home' ? '/' : '/' . $identifier;

            $urls[] = [
                'loc'      => $baseUrl . $path,
                'lastmod'  => $this->templateModified((string) ($page['template'] ?? '')),
                'priority' => number_format($this->priorityFor($identifier), 1),
            ];
        }

        foreach (self::EXTRA as $path => $priority) {
            $urls[] = [
                'loc'      => $baseUrl . $path,
                'lastmod'  => null,
                'priority' => number_format($priority, 1),
            ];
        }

        return $urls;
    }

    private function priorityFor(string $identifier): float
    {
        if (isset(self::PRIORITY[$identifier])) {
            return self::PRIORITY[$identifier];
        }

        return str_starts_with($identifier, 'solar-in-') ? self::AREA_PRIORITY : 0.5;
    }

    /**
     * lastmod from the template's own modification time.
     *
     * Not a stored date, because there is no editorial workflow here to set
     * one, and a lastmod somebody has to remember to update is a lastmod that
     * lies. The file changing IS the page changing -- these are static
     * templates -- so the filesystem already holds the honest answer.
     *
     * Four of these pages share one template, so all four move together when it
     * is edited. That is accurate: editing service-area.phtml does change all
     * four pages.
     */
    private function templateModified(string $template): ?string
    {
        if ($template === '') {
            return null;
        }

        try {
            $file = $this->templates->resolve($template);
        } catch (\Throwable) {
            // A sitemap is not worth a 500. Omitting lastmod is legal and the
            // URL still gets submitted, which is the part that matters.
            return null;
        }

        $mtime = is_file($file) ? filemtime($file) : false;

        return $mtime === false ? null : gmdate('Y-m-d', $mtime);
    }
}
