<?php

declare(strict_types=1);

namespace EcoLife\Lead\Model\ResourceModel\Lead;

use EcoLife\Core\Model\AbstractModel;
use EcoLife\Core\Model\ResourceModel\AbstractCollection;
use EcoLife\Core\Model\ResourceModel\AbstractResource;
use EcoLife\Lead\Model\Lead;
use EcoLife\Lead\Model\ResourceModel\Lead as LeadResource;

/**
 * @extends AbstractCollection<Lead>
 */
final class Collection extends AbstractCollection
{
    private LeadResource $leadResource;

    public function getResource(): AbstractResource
    {
        return $this->leadResource ??= new LeadResource();
    }

    protected function newModel(array $row): AbstractModel
    {
        return new Lead($row);
    }

    /**
     * The admin grid's filter set, applied from one array so the grid template
     * and the CSV export cannot diverge in what they show.
     *
     * @param array<string, string> $filters
     */
    public function applyGridFilters(array $filters): self
    {
        if (($status = trim($filters['status'] ?? '')) !== '') {
            $this->addFieldToFilter('status', $status);
        }

        if (($type = trim($filters['lead_type'] ?? '')) !== '') {
            $this->addFieldToFilter('lead_type', $type);
        }

        if (($from = trim($filters['from'] ?? '')) !== '') {
            $this->addFieldToFilter('created_at', ['gteq' => $from . ' 00:00:00']);
        }

        if (($to = trim($filters['to'] ?? '')) !== '') {
            $this->addFieldToFilter('created_at', ['lteq' => $to . ' 23:59:59']);
        }

        // One search box across name, phone, email and city. Conditions AND
        // together in this collection, so a multi-column OR would need the
        // nesting the base class deliberately does not have -- searching the
        // most useful single column at a time is the honest trade.
        if (($search = trim($filters['q'] ?? '')) !== '') {
            $field = match (true) {
                (bool) preg_match('/^\d+$/', $search)      => 'phone',
                str_contains($search, '@')                 => 'email',
                default                                    => 'name',
            };
            $this->addFieldToFilter($field, ['like' => '%' . $search . '%']);
        }

        return $this;
    }
}
