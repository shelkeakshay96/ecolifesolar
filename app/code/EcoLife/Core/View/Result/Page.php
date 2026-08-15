<?php

declare(strict_types=1);

namespace EcoLife\Core\View\Result;

use EcoLife\Core\App\Context;
use EcoLife\Core\App\Response;
use EcoLife\Core\Controller\ResultInterface;
use EcoLife\Core\View\Element\AbstractBlock;
use EcoLife\Core\View\Element\Template;

/**
 * A full HTML page: the area's layout template with one content block dropped
 * into it.
 *
 * This is the replacement for Magento's layout XML, and it is deliberately
 * much less: a controller names its content block and template directly, and
 * the layout is an ordinary .phtml in EcoLife_Theme that asks for the slots it
 * wants. Nothing is merged, nothing is configured, and the whole composition of
 * a page is visible in the controller that built it.
 */
final class Page implements ResultInterface
{
    private string $title = 'EcoLifeSolar';
    private string $metaDescription = '';
    private string $bodyClass = '';
    private string $robots = 'index,follow';
    private string $canonicalUrl = '';
    private string $socialImage = '';
    private int $statusCode = 200;

    private ?AbstractBlock $content = null;

    public function __construct(private readonly Context $context)
    {
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setMetaDescription(string $description): self
    {
        $this->metaDescription = $description;
        return $this;
    }

    public function getMetaDescription(): string
    {
        return $this->metaDescription;
    }

    public function setBodyClass(string $class): self
    {
        $this->bodyClass = $class;
        return $this;
    }

    public function getBodyClass(): string
    {
        return $this->bodyClass;
    }

    public function setRobots(string $robots): self
    {
        $this->robots = $robots;
        return $this;
    }

    /**
     * The admin panel is never indexable, whatever a controller asked for.
     *
     * robots.txt already disallows /admin, but robots.txt is a crawl
     * instruction, not an index instruction: a URL somebody links to can still
     * be listed from the link alone, without ever being fetched. The meta tag
     * is what actually keeps it out, and deciding it here rather than in each
     * of the twenty-odd admin controllers means a new one cannot forget.
     */
    public function getRobots(): string
    {
        return $this->context->getArea()->isAdmin() ? 'noindex,nofollow' : $this->robots;
    }

    /**
     * The one address this page should be indexed under.
     *
     * Left unset, it is the current path made absolute -- which is right for
     * every page the site currently has, because each one answers on exactly
     * one URL. Set it explicitly when that stops being true: a filtered or
     * paginated listing needs to point at the unfiltered page rather than
     * competing with it.
     */
    public function setCanonicalUrl(string $url): self
    {
        $this->canonicalUrl = $url;
        return $this;
    }

    public function getCanonicalUrl(): string
    {
        if ($this->canonicalUrl !== '') {
            return $this->canonicalUrl;
        }

        $url  = $this->context->getUrl();
        $path = $this->context->getRequest()->getPathInfo();

        // Query strings are dropped deliberately. Nothing on the public site
        // varies its content by one, so ?fbclid=... arriving from a shared link
        // must not become a second indexable address for the same page.
        return rtrim($url->getBaseUrl(), '/') . ($path === '' ? '/' : $path);
    }

    /** Absolute URL of the image social platforms should use for this page. */
    public function setSocialImage(string $url): self
    {
        $this->socialImage = $url;
        return $this;
    }

    public function getSocialImage(): string
    {
        $url = $this->context->getUrl();

        return $this->socialImage !== ''
            ? $this->socialImage
            : rtrim($url->getBaseUrl(), '/') . $url->getStaticUrl('images/panels.jpg');
    }

    public function setHttpResponseCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * @param class-string<AbstractBlock> $class
     * @param array<string, mixed> $data
     */
    public function setContent(string $class, string $template, array $data = []): self
    {
        $block = new $class($this->context, $data);
        $this->content = $block->setTemplate($template);
        return $this;
    }

    /** Content that needs no block class of its own. */
    public function setContentTemplate(string $template, array $data = []): self
    {
        return $this->setContent(Template::class, $template, $data);
    }

    public function setContentBlock(AbstractBlock $block): self
    {
        $this->content = $block;
        return $this;
    }

    public function getContentHtml(): string
    {
        return $this->content?->toHtml() ?? '';
    }

    public function renderResult(Response $response): void
    {
        $layout = new Layout($this->context, ['page' => $this]);
        $layout->setTemplate(
            $this->context->getArea()->isAdmin()
                ? 'EcoLife_Theme::layout/admin.phtml'
                : 'EcoLife_Theme::layout/default.phtml'
        );

        $response->setHttpResponseCode($this->statusCode)
                 ->setHeader('Content-Type', 'text/html; charset=UTF-8')
                 ->setBody($layout->toHtml());
    }
}
