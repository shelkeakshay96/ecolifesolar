<?php

declare(strict_types=1);

namespace EcoLife\Lead\Model\ResourceModel\StatusHistory;

use EcoLife\Core\Model\AbstractModel;
use EcoLife\Core\Model\ResourceModel\AbstractCollection;
use EcoLife\Core\Model\ResourceModel\AbstractResource;
use EcoLife\Lead\Model\ResourceModel\StatusHistory as StatusHistoryResource;
use EcoLife\Lead\Model\StatusHistory;

/**
 * @extends AbstractCollection<StatusHistory>
 */
final class Collection extends AbstractCollection
{
    private StatusHistoryResource $historyResource;

    public function getResource(): AbstractResource
    {
        return $this->historyResource ??= new StatusHistoryResource();
    }

    protected function newModel(array $row): AbstractModel
    {
        return new StatusHistory($row);
    }

    public function forLead(int $leadId): self
    {
        $this->addFieldToFilter('lead_id', $leadId)->addOrder('created_at', self::SORT_DESC);
        return $this;
    }
}
