<?php

declare(strict_types=1);

namespace EcoLife\Gallery\Model\ResourceModel;

use EcoLife\Core\Model\ResourceModel\AbstractResource;

final class Item extends AbstractResource
{
    protected string $table = 'gallery_item';
    protected string $idFieldName = 'item_id';

    protected array $fields = [
        'item_id', 'title', 'description', 'image_path', 'thumbnail_path',
        'location', 'system_size_kw', 'install_date', 'category',
        'sort_order', 'is_active', 'created_at', 'updated_at',
    ];
}
