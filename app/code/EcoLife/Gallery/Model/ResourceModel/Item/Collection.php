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

    /**
     * Active items whose location mentions a town, for the service-area pages.
     *
     * A LIKE rather than a foreign key to a towns table: location is free text
     * the family types, and it will read "Karad, Maharashtra" or "near Karad"
     * or just "Karad" depending on the day. A substring match is the honest
     * shape of that data. It also means a page can quietly show nothing, which
     * the template handles.
     */
    public function forTown(string $town, ?int $limit = null): self
    {
        $this->addFieldToFilter('is_active', 1)
             ->addFieldToFilter('location', ['like' => '%' . $town . '%'])
             ->addOrder('sort_order', self::SORT_ASC)
             ->addOrder('item_id', self::SORT_DESC);

        if ($limit !== null && $limit > 0) {
            $this->setPageSize($limit)->setCurPage(1);
        }

        return $this;
    }
}
