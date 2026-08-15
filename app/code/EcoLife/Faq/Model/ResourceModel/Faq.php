<?php

declare(strict_types=1);

namespace EcoLife\Faq\Model\ResourceModel;

use EcoLife\Core\Model\ResourceModel\AbstractResource;

final class Faq extends AbstractResource
{
    protected string $table = 'faq';
    protected string $idFieldName = 'faq_id';

    /**
     * The column whitelist. Every identifier that reaches SQL is checked
     * against this, so both timestamps have to be listed even though nothing
     * writes them -- addOrder('created_at') throws otherwise.
     */
    protected array $fields = [
        'faq_id', 'question', 'answer', 'link_url', 'link_label',
        'sort_order', 'is_active', 'created_at', 'updated_at',
    ];
}
