<?php

declare(strict_types=1);

namespace EcoLife\Lead\Block\Adminhtml;

use EcoLife\Core\View\Element\AbstractBlock;
use EcoLife\Lead\Model\Lead;
use EcoLife\Lead\Model\ResourceModel\Lead\Collection;

/**
 * The lead grid.
 *
 * Filters live in the query string so a filtered view can be bookmarked and
 * shared -- "the four Karad enquiries from last week" is a URL, not a sequence
 * of clicks to repeat.
 */
final class Grid extends AbstractBlock
{
    private const PAGE_SIZE = 25;

    private ?Collection $collection = null;

    public function getCollection(): Collection
    {
        if ($this->collection !== null) {
            return $this->collection;
        }

        $collection = (new Collection())
            ->applyGridFilters($this->getFilters())
            ->addOrder('created_at', Collection::SORT_DESC)
            ->setPageSize(self::PAGE_SIZE)
            ->setCurPage($this->getCurrentPage());

        return $this->collection = $collection;
    }

    /** @return array<string, string> */
    public function getFilters(): array
    {
        $request = $this->getRequest();

        return [
            'status'    => (string) $request->getQuery('status', ''),
            'lead_type' => (string) $request->getQuery('lead_type', ''),
            'from'      => $this->date((string) $request->getQuery('from', '')),
            'to'        => $this->date((string) $request->getQuery('to', '')),
            'q'         => trim((string) $request->getQuery('q', '')),
        ];
    }

    /** Only accept a real ISO date; anything else is dropped, not passed on. */
    private function date(string $value): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    public function hasActiveFilters(): bool
    {
        return array_filter($this->getFilters()) !== [];
    }

    public function getCurrentPage(): int
    {
        return max(1, (int) $this->getRequest()->getQuery('p', 1));
    }

    public function getTotalCount(): int
    {
        return $this->getCollection()->getSize();
    }

    public function getLastPage(): int
    {
        return $this->getCollection()->getLastPageNumber();
    }

    /** @return array<string, string> */
    public function getStatusOptions(): array
    {
        return Lead::STATUSES;
    }

    /** @return array<string, string> */
    public function getTypeOptions(): array
    {
        return array_combine(Lead::TYPES, array_map('ucfirst', Lead::TYPES));
    }

    public function getViewUrl(Lead $lead): string
    {
        return $this->getUrl('leads/lead/view', ['id' => (int) $lead->getId()]);
    }

    public function getExportUrl(): string
    {
        return $this->getUrl('leads/lead/export', ['_query' => array_filter($this->getFilters())]);
    }

    /** Preserves the current filters while changing the page. */
    public function getPageUrl(int $page): string
    {
        $query = array_filter($this->getFilters());
        $query['p'] = $page;

        return $this->getUrl('leads', ['_query' => $query]);
    }

    public function getStatusClass(string $status): string
    {
        return match ($status) {
            'new'                     => 'bg-brand-50 text-brand-600',
            'converted'               => 'bg-emerald-50 text-emerald-700',
            'not_interested', 'junk'  => 'bg-slate-100 text-slate-400',
            'quotation_sent',
            'negotiating'             => 'bg-indigo-50 text-indigo-700',
            default                   => 'bg-slate-100 text-slate-700',
        };
    }
}
