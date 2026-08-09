<?php

declare(strict_types=1);

namespace EcoLife\Gallery\Block\Adminhtml;

use EcoLife\Core\View\Element\AbstractBlock;
use EcoLife\Gallery\Model\Item;
use EcoLife\Gallery\Model\ResourceModel\Item\Collection;

final class Manage extends AbstractBlock
{
    /** @return list<Item> */
    public function getItems(): array
    {
        return (new Collection())
            ->addOrder('sort_order', Collection::SORT_ASC)
            ->addOrder('item_id', Collection::SORT_DESC)
            ->getItems();
    }

    /** @return array<string, string> */
    public function getCategories(): array
    {
        return Item::CATEGORIES;
    }

    public function getSaveUrl(): string
    {
        return $this->getUrl('gallery/item/save');
    }

    public function getDeleteUrl(Item $item): string
    {
        return $this->getUrl('gallery/item/delete', ['id' => (int) $item->getId()]);
    }
}
