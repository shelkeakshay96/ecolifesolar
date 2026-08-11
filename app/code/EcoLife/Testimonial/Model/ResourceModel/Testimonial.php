<?php

declare(strict_types=1);

namespace EcoLife\Testimonial\Model\ResourceModel;

use EcoLife\Core\Model\ResourceModel\AbstractResource;

final class Testimonial extends AbstractResource
{
    protected string $table = 'testimonial';
    protected string $idFieldName = 'testimonial_id';

    /**
     * The column whitelist. Every identifier that reaches SQL is checked
     * against this, so both timestamps have to be listed even though nothing
     * writes them -- addOrder('created_at') throws otherwise.
     */
    protected array $fields = [
        'testimonial_id', 'customer_name', 'location', 'business', 'photo_path',
        'quote', 'system_size_kw', 'language', 'sort_order', 'is_active',
        'created_at', 'updated_at',
    ];
}
