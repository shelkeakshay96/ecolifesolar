<?php

declare(strict_types=1);

namespace EcoLife\Seo\Model;

use EcoLife\Cms\App\Router\Page as PageRouter;
use EcoLife\Cms\Block\Page as CmsPage;
use EcoLife\Core\App\Context;
use EcoLife\Core\View\TemplateResolver;
use Throwable;

/**
 * Every public URL worth submitting to a search engine, with the images on it.
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
 *
 * Images are attached per URL because Google Images will not reliably discover
 * a photograph from page markup alone -- particularly one that is lazy-loaded,
 * which every image on this site is. Listing them here is the supported way of
 * saying "these specific files exist, on this page, and here is what they show".
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
        'faq'      => 0.8,
        'contact'  => 0.8,
        'about'    => 0.7,
    ];

    /** Service-area pages: the local-search reason this site exists. */
    private const AREA_PRIORITY = 0.9;

    public function __construct(private readonly Context $context)
    {
    }

    /**
     * @return list<array{loc: string, lastmod: string|null, priority: string,
     *                    images: list<array{loc: string, title: string, caption: string}>}>
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
                'images'   => $identifier === 'about' ? $this->teamImages($baseUrl) : [],
            ];
        }

        foreach (self::EXTRA as $path => $priority) {
            $urls[] = [
                'loc'      => $baseUrl . $path,
                'lastmod'  => null,
                'priority' => number_format($priority, 1),
                'images'   => $path === '/gallery' ? $this->galleryImages($baseUrl) : [],
            ];
        }

        return $urls;
    }

    /**
     * The three founder photographs, on the page that shows them.
     *
     * These are the reason the image extension is here at all: somebody
     * searching a founder by name should find the face, and a lazy-loaded
     * <img> three screens down an About page is not something Google Images
     * finds on its own.
     *
     * @return list<array{loc: string, title: string, caption: string}>
     */
    private function teamImages(string $baseUrl): array
    {
        $images = [];

        foreach ((new CmsPage($this->context))->getTeam() as $person) {
            $path = ltrim((string) $person['photo'], '/');

            // Same rule as the team cards and the JSON-LD: a file that is not
            // on disk is not advertised. Submitting a 404 to Google Images is
            // a reported error rather than a harmless miss.
            if (!is_file(BP . '/pub/' . $path)) {
                continue;
            }

            $images[] = [
                'loc'     => $baseUrl . $this->context->getUrl()->getStaticUrl($path),
                'title'   => (string) $person['name'],
                'caption' => (string) ($person['photo_caption'] ?? ''),
            ];
        }

        return $images;
    }

    /**
     * The installation photographs.
     *
     * Guarded by class_exists rather than declared as a dependency: EcoLife_Seo
     * sequences after Cms alone, and a sitemap is not worth taking down if
     * EcoLife_Gallery is ever switched off in app/etc/config.php. Absent
     * gallery, this is simply an empty list.
     *
     * @return list<array{loc: string, title: string, caption: string}>
     */
    private function galleryImages(string $baseUrl): array
    {
        $collectionClass = 'EcoLife\\Gallery\\Model\\ResourceModel\\Item\\Collection';

        if (!class_exists($collectionClass)) {
            return [];
        }

        $images = [];

        try {
            /** @var iterable<object> $items */
            $items = (new $collectionClass())->forDisplay();

            foreach ($items as $item) {
                if (!$item->imageExists()) {
                    continue;
                }

                $meta = array_filter([
                    $item->getSizeLabel(),
                    $item->getLocation(),
                    $item->getCategoryLabel(),
                ]);

                $images[] = [
                    'loc'     => $baseUrl . $this->context->getUrl()->getMediaUrl($item->getImagePath()),
                    'title'   => (string) $item->getTitle(),
                    'caption' => $meta === []
                        ? (string) $item->getTitle()
                        : $item->getTitle() . ' - ' . implode(', ', $meta),
                ];
            }
        } catch (Throwable) {
            // A gallery query that fails must not take the sitemap with it. The
            // page URLs are the part search engines actually need.
            return [];
        }

        return $images;
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
            $file = (new TemplateResolver($this->context->getArea()))->resolve($template);
        } catch (Throwable) {
            // A sitemap is not worth a 500. Omitting lastmod is legal and the
            // URL still gets submitted, which is the part that matters.
            return null;
        }

        $mtime = is_file($file) ? filemtime($file) : false;

        return $mtime === false ? null : gmdate('Y-m-d', $mtime);
    }
}
