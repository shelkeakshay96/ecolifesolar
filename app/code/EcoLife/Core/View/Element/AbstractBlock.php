<?php

declare(strict_types=1);

namespace EcoLife\Core\View\Element;

use EcoLife\Core\App\Context;
use EcoLife\Core\Model\Settings;
use RuntimeException;
use Throwable;

/**
 * A view model: the data and logic for exactly one .phtml file.
 *
 * Templates receive $block (this object) and nothing else. There is no
 * extract() of variables into the template scope, so every value a template
 * prints is traceable to a method on a class.
 *
 * The escaping surface lives here rather than in a helper because that is what
 * makes `bin/ecolife lint:templates` possible: the linter can insist that every
 * `<?=` in every template contains one of these method names.
 */
abstract class AbstractBlock
{
    protected string $template = '';

    /** @var array<string, AbstractBlock> */
    private array $children = [];

    /** @param array<string, mixed> $data */
    public function __construct(
        protected readonly Context $context,
        protected array $data = [],
    ) {
        $this->construct();
    }

    /** Subclass hook, so constructors do not have to be redeclared. */
    protected function construct(): void
    {
    }

    // ------------------------------------------------------------------- data

    public function getData(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function setData(string $key, mixed $value): static
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function hasData(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    // --------------------------------------------------------------- template

    public function setTemplate(string $template): static
    {
        $this->template = $template;
        return $this;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * Render this block's template.
     *
     * Output buffering is closed in a finally, so a template that throws
     * halfway through cannot leave a partial page in the buffer to be flushed
     * over the error page.
     */
    public function toHtml(): string
    {
        if ($this->template === '') {
            return '';
        }

        $file = $this->context->getTemplateResolver()->resolve($this->template);

        $level = ob_get_level();
        ob_start();

        try {
            $block = $this;
            include $file;
            return (string) ob_get_clean();
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw new RuntimeException(
                "Error rendering template {$this->template}: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function __toString(): string
    {
        return $this->toHtml();
    }

    // --------------------------------------------------------------- children

    /**
     * Register a named child to be pulled out later with getChildHtml().
     *
     * @param class-string<AbstractBlock> $class
     * @param array<string, mixed> $data
     */
    public function addChild(string $name, string $class, string $template, array $data = []): static
    {
        $child = new $class($this->context, $data);
        $child->setTemplate($template);
        $this->children[$name] = $child;
        return $this;
    }

    public function getChildHtml(string $name): string
    {
        return isset($this->children[$name]) ? $this->children[$name]->toHtml() : '';
    }

    public function getChild(string $name): ?AbstractBlock
    {
        return $this->children[$name] ?? null;
    }

    /**
     * Build and render a block inline. This is how cross-module composition
     * happens -- an explicit call in a template, not a configured reference:
     *
     *   <?= $block->renderChild(\EcoLife\Lead\Block\Form::class,
     *                           'EcoLife_Lead::form/lead.phtml', ['source' => 'contact']) ?>
     *
     * @param class-string<AbstractBlock> $class
     * @param array<string, mixed> $data
     */
    public function renderChild(string $class, string $template, array $data = []): string
    {
        $child = new $class($this->context, $data);
        return $child->setTemplate($template)->toHtml();
    }

    // -------------------------------------------------------------- accessors

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getRequest(): \EcoLife\Core\App\Request
    {
        return $this->context->getRequest();
    }

    public function getUrl(string $path = '', array $params = []): string
    {
        return $this->context->getUrl()->getUrl($path, $params);
    }

    public function getFrontendUrl(string $path = '', array $params = []): string
    {
        return $this->context->getUrl()->getFrontendUrl($path, $params);
    }

    /** Cache-busted URL for a file under pub/. */
    public function getStaticUrl(string $file): string
    {
        return $this->context->getUrl()->getStaticUrl($file);
    }

    public function getMediaUrl(string $file): string
    {
        return $this->context->getUrl()->getMediaUrl($file);
    }

    public function getFormKey(): string
    {
        return $this->context->getFormKey()->get();
    }

    /** A value the family can edit from the admin panel. */
    public function getSetting(string $path, string $default = ''): string
    {
        return Settings::get($path, $default);
    }

    public function isAdminArea(): bool
    {
        return $this->context->getArea()->isAdmin();
    }

    // --------------------------------------------------------------- escaping

    public function escapeHtml(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    public function escapeHtmlAttr(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /**
     * For a URL going into href or src. Anything with a scheme other than
     * http/https/mailto/tel becomes '#', which stops javascript: and data:
     * URLs arriving from the database via an admin-editable field.
     */
    public function escapeUrl(mixed $value): string
    {
        $url = trim((string) $value);

        if ($url === '') {
            return '';
        }

        if (preg_match('#^([a-z][a-z0-9+.-]*):#i', $url, $matches)
            && !in_array(strtolower($matches[1]), ['http', 'https', 'mailto', 'tel'], true)) {
            return '#';
        }

        return $this->escapeHtmlAttr($url);
    }

    /** For a string being embedded inside a <script> block or an inline handler. */
    public function escapeJs(mixed $value): string
    {
        return (string) json_encode(
            (string) $value,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );
    }

    /** Formatted for Indian readers: 1,23,456 rather than 123,456. */
    public function formatCurrency(float|int|string $amount, bool $withSymbol = true): string
    {
        $amount   = (float) $amount;
        $rounded  = number_format(abs($amount), 0, '.', '');
        $lastThree = substr($rounded, -3);
        $rest      = substr($rounded, 0, -3);

        if ($rest !== '') {
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) ?? $rest;
            $formatted = $rest . ',' . $lastThree;
        } else {
            $formatted = $lastThree;
        }

        $sign = $amount < 0 ? '-' : '';

        return $sign . ($withSymbol ? '₹' : '') . $formatted;
    }

    public function formatDate(?string $datetime, string $format = 'd M Y'): string
    {
        if ($datetime === null || $datetime === '' || str_starts_with($datetime, '0000')) {
            return '';
        }
        $timestamp = strtotime($datetime);
        return $timestamp === false ? '' : date($format, $timestamp);
    }
}
