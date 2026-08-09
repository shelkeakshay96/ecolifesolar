<?php

declare(strict_types=1);

namespace EcoLife\Gallery\Model\ResourceModel\Item;

use EcoLife\Core\Model\AbstractModel;
use EcoLife\Core\Model\ResourceModel\AbstractCollection;
use EcoLife\Core\Model\ResourceModel\AbstractResource;
use EcoLife\Gallery\Model\Item;
use EcoLife\Gallery\Model\ResourceModel\Item as ItemResource;

/**
 * @extends AbstractCollection<Item>
 */
final class Collection extends AbstractCollection
{
    private ItemResource $itemResource;

    public function getResource(): AbstractResource
    {
        return $this->itemResource ??= new ItemResource();
    }

    protected function newModel(array $row): AbstractModel
    {
        return new Item($row);
    }

    /** What the public gallery shows: active items, in the family's chosen order. */
    public function forDisplay(?string $category = null): self
    {
        $this->addFieldToFilter('is_active', 1);

        if ($category !== null && $category !== '') {
            $this->addFieldToFilter('category', $category);
        }

        $this->addOrder('sort_order', self::SORT_ASC)->addOrder('item_id', self::SORT_DESC);

        return $this;
    }
}
