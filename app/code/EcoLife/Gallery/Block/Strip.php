<?php

declare(strict_types=1);

namespace EcoLife\Gallery\Block;

use EcoLife\Core\View\Element\AbstractBlock;
use EcoLife\Gallery\Model\Item;
use EcoLife\Gallery\Model\ResourceModel\Item\Collection;

/**
 * A short row of completed projects, composed into other pages.
 *
 * Two callers, one shape: the home page shows the most recent handful directly
 * under the hero, and each service-area page shows the one or two projects in
 * that town. Both want "a few photos that link to the gallery", which is not
 * what Block\Grid does -- Grid owns the filterable gallery page itself.
 *
 * Accepts `limit` and, optionally, `town`.
 */
final class Strip extends AbstractBlock
{
    /** @return list<Item> */
    public function getItems(): array
    {
        $limit = (int) $this->getData('limit', 4);
        $town  = trim((string) $this->getData('town', ''));

        $collection = new Collection();

        if ($town !== '') {
            $collection->forTown($town, $limit);
        } else {
            $collection->forDisplay();

            if ($limit > 0) {
                $collection->setPageSize($limit)->setCurPage(1);
            }
        }

        // A row of "image unavailable" placeholders is worse than a shorter row,
        // so items whose file is missing are dropped rather than rendered. The
        // gallery page itself keeps them, because there the empty frame at least
        // tells the family something needs re-uploading.
        return array_values(array_filter(
            $collection->getItems(),
            static fn(Item $item): bool => $item->imageExists()
        ));
    }

    public function getHeading(): string
    {
        return (string) $this->getData('heading', 'Recent installations');
    }

    public function getIntro(): string
    {
        return (string) $this->getData('intro', '');
    }
}
