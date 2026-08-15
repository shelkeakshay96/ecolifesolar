<?php

declare(strict_types=1);

namespace EcoLife\Faq\Block;

use EcoLife\Core\View\Element\AbstractBlock;
use EcoLife\Faq\Model\Faq;
use EcoLife\Faq\Model\ResourceModel\Faq\Collection;

final class Faqs extends AbstractBlock
{
    /**
     * The pages.php identifier of the page that carries the FAQ.
     *
     * Named here so EcoLife_Seo can ask the same question this block answers,
     * rather than both modules hardcoding the string separately and drifting
     * apart the day somebody renames the page.
     */
    public const PAGE_IDENTIFIER = 'faq';

    /**
     * Visible questions, in the family's order.
     *
     * Loaded once per request: the template renders these and EcoLife_Seo turns
     * the same rows into the FAQPage node, and there is no reason to ask the
     * database twice for one page.
     *
     * @return list<Faq>
     */
    public function getFaqs(): array
    {
        static $items = null;

        return $items ??= (new Collection())->forDisplay()->getItems();
    }

    public function hasFaqs(): bool
    {
        return $this->getFaqs() !== [];
    }
}
