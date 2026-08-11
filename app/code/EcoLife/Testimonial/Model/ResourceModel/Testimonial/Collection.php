<?php

declare(strict_types=1);

namespace EcoLife\Testimonial\Model\ResourceModel\Testimonial;

use EcoLife\Core\Model\AbstractModel;
use EcoLife\Core\Model\ResourceModel\AbstractCollection;
use EcoLife\Core\Model\ResourceModel\AbstractResource;
use EcoLife\Testimonial\Model\Testimonial;
use EcoLife\Testimonial\Model\ResourceModel\Testimonial as TestimonialResource;

/**
 * @extends AbstractCollection<Testimonial>
 */
final class Collection extends AbstractCollection
{
    private TestimonialResource $testimonialResource;

    public function getResource(): AbstractResource
    {
        return $this->testimonialResource ??= new TestimonialResource();
    }

    protected function newModel(array $row): AbstractModel
    {
        return new Testimonial($row);
    }

    /** What the public site shows: active quotes, in the family's chosen order. */
    public function forDisplay(?int $limit = null): self
    {
        $this->addFieldToFilter('is_active', 1)
             ->addOrder('sort_order', self::SORT_ASC)
             ->addOrder('testimonial_id', self::SORT_DESC);

        if ($limit !== null && $limit > 0) {
            $this->setPageSize($limit)->setCurPage(1);
        }

        return $this;
    }

    /** Everything, newest edit first. The admin screen wants inactive rows too. */
    public function forAdmin(): self
    {
        $this->addOrder('sort_order', self::SORT_ASC)->addOrder('testimonial_id', self::SORT_DESC);

        return $this;
    }
}
