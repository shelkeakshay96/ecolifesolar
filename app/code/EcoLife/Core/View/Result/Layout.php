<?php

declare(strict_types=1);

namespace EcoLife\Core\View\Result;

use EcoLife\Core\App\Messages;
use EcoLife\Core\View\Element\AbstractBlock;

/**
 * The block behind the layout template. It gives layout/default.phtml the few
 * things it needs -- the page metadata, the content HTML, and the flash
 * messages -- without the template reaching into the Context itself.
 */
final class Layout extends AbstractBlock
{
    public function getPage(): Page
    {
        return $this->getData('page');
    }

    public function getTitle(): string
    {
        return $this->getPage()->getTitle();
    }

    public function getMetaDescription(): string
    {
        return $this->getPage()->getMetaDescription();
    }

    public function getBodyClass(): string
    {
        return $this->getPage()->getBodyClass();
    }

    public function getRobots(): string
    {
        return $this->getPage()->getRobots();
    }

    public function getContentHtml(): string
    {
        return $this->getPage()->getContentHtml();
    }

    /**
     * Reading messages empties them, so this is called once per render and the
     * result cached -- otherwise a layout that asked twice would show them once
     * and lose them.
     *
     * @return list<array{type: string, text: string}>
     */
    public function getMessages(): array
    {
        if (!$this->hasData('messages')) {
            $this->setData('messages', $this->context->getMessages()->getAll());
        }
        return $this->getData('messages', []);
    }

    /** Tailwind classes for a message banner, by severity. */
    public function getMessageClass(string $type): string
    {
        return match ($type) {
            Messages::SUCCESS => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            Messages::ERROR   => 'border-red-200 bg-red-50 text-red-800',
            Messages::WARNING => 'border-amber-200 bg-amber-50 text-amber-800',
            default           => 'border-slate-200 bg-slate-50 text-slate-700',
        };
    }

    public function getYear(): string
    {
        return date('Y');
    }
}
