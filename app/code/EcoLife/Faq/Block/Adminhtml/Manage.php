<?php

declare(strict_types=1);

namespace EcoLife\Faq\Block\Adminhtml;

use EcoLife\Core\View\Element\AbstractBlock;
use EcoLife\Faq\Model\Faq;
use EcoLife\Faq\Model\ResourceModel\Faq\Collection;

final class Manage extends AbstractBlock
{
    /** @return list<Faq> */
    public function getFaqs(): array
    {
        return (new Collection())->forAdmin()->getItems();
    }

    /**
     * The tokens an answer may contain, for the help text on the form.
     *
     * @return array<string, string>
     */
    public function getTokens(): array
    {
        return Faq::TOKENS;
    }

    public function getSaveUrl(): string
    {
        return $this->getUrl('faqs/faq/save');
    }

    public function getDeleteUrl(Faq $faq): string
    {
        return $this->getUrl('faqs/faq/delete', ['id' => (int) $faq->getId()]);
    }

    /** Where the questions actually appear, for the "view page" link. */
    public function getPageUrl(): string
    {
        return $this->getFrontendUrl('faq');
    }
}
