<?php

declare(strict_types=1);

namespace EcoLife\Testimonial\Block;

use EcoLife\Core\View\Element\AbstractBlock;
use EcoLife\Testimonial\Model\ResourceModel\Testimonial\Collection;
use EcoLife\Testimonial\Model\Testimonial;

/**
 * The public testimonial section, composed into the home page by an explicit
 * renderChild() call.
 *
 * There is no controller and no /testimonials route: this is a section, not a
 * page. If a standalone page is ever wanted, it needs a frontend routes.xml and
 * a controller, and neither exists on purpose.
 */
final class Testimonials extends AbstractBlock
{
    /** @return list<Testimonial> */
    public function getTestimonials(): array
    {
        $limit = (int) $this->getData('limit', 3);

        return (new Collection())->forDisplay($limit > 0 ? $limit : null)->getItems();
    }
}
