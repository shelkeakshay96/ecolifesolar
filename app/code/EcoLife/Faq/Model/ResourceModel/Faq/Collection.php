<?php

declare(strict_types=1);

namespace EcoLife\Faq\Model\ResourceModel\Faq;

use EcoLife\Core\Model\AbstractModel;
use EcoLife\Core\Model\ResourceModel\AbstractCollection;
use EcoLife\Core\Model\ResourceModel\AbstractResource;
use EcoLife\Faq\Model\Faq;
use EcoLife\Faq\Model\ResourceModel\Faq as FaqResource;

/**
 * @extends AbstractCollection<Faq>
 */
final class Collection extends AbstractCollection
{
    private FaqResource $faqResource;

    public function getResource(): AbstractResource
    {
        return $this->faqResource ??= new FaqResource();
    }

    protected function newModel(array $row): AbstractModel
    {
        return new Faq($row);
    }

    /**
     * What the public page shows: visible questions, in the family's order.
     *
     * Ordered by sort_order and then faq_id ASC rather than DESC -- unlike the
     * testimonial list, an FAQ reads top to bottom as a sequence, so two
     * questions sharing a sort order should appear in the order they were
     * written rather than newest first.
     */
    public function forDisplay(): self
    {
        $this->addFieldToFilter('is_active', 1)
             ->addOrder('sort_order', self::SORT_ASC)
             ->addOrder('faq_id', self::SORT_ASC);

        return $this;
    }

    /** Everything, including hidden rows, which the admin screen needs. */
    public function forAdmin(): self
    {
        $this->addOrder('sort_order', self::SORT_ASC)->addOrder('faq_id', self::SORT_ASC);

        return $this;
    }
}
