<?php

declare(strict_types=1);

namespace EcoLife\Gallery\Block;

use EcoLife\Core\View\Element\AbstractBlock;
use EcoLife\Gallery\Model\Item;
use EcoLife\Gallery\Model\ResourceModel\Item\Collection;

final class Grid extends AbstractBlock
{
    /** @return list<Item> */
    public function getItems(): array
    {
        return (new Collection())->forDisplay($this->getCategory())->getItems();
    }

    public function getCategory(): string
    {
        $category = (string) $this->getRequest()->getQuery('category', '');
        return array_key_exists($category, Item::CATEGORIES) ? $category : '';
    }

    /** @return array<string, string> */
    public function getCategories(): array
    {
        return Item::CATEGORIES;
    }

    public function getCategoryUrl(string $category): string
    {
        return $category === ''
            ? $this->getFrontendUrl('gallery')
            : $this->getFrontendUrl('gallery') . '?category=' . rawurlencode($category);
    }
}
