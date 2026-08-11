<?php

declare(strict_types=1);

namespace EcoLife\Testimonial\Block\Adminhtml;

use EcoLife\Core\View\Element\AbstractBlock;
use EcoLife\Testimonial\Model\ResourceModel\Testimonial\Collection;
use EcoLife\Testimonial\Model\Testimonial;

final class Manage extends AbstractBlock
{
    /** @return list<Testimonial> */
    public function getTestimonials(): array
    {
        return (new Collection())->forAdmin()->getItems();
    }

    /** @return array<string, string> */
    public function getLanguages(): array
    {
        return Testimonial::LANGUAGES;
    }

    public function getSaveUrl(): string
    {
        return $this->getUrl('testimonials/testimonial/save');
    }

    public function getDeleteUrl(Testimonial $testimonial): string
    {
        return $this->getUrl('testimonials/testimonial/delete', ['id' => (int) $testimonial->getId()]);
    }
}
