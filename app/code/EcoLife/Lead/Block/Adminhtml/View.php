<?php

declare(strict_types=1);

namespace EcoLife\Lead\Block\Adminhtml;

use EcoLife\Core\View\Element\AbstractBlock;
use EcoLife\Lead\Model\Lead;
use EcoLife\Lead\Model\ResourceModel\StatusHistory\Collection as HistoryCollection;

final class View extends AbstractBlock
{
    public function getLead(): Lead
    {
        return $this->getData('lead');
    }

    /** @return list<\EcoLife\Lead\Model\StatusHistory> */
    public function getHistory(): array
    {
        return (new HistoryCollection())->forLead((int) $this->getLead()->getId())->getItems();
    }

    /** @return array<string, string> */
    public function getStatusOptions(): array
    {
        return Lead::STATUSES;
    }

    /** @return array<string, string> */
    public function getPriorityOptions(): array
    {
        return Lead::PRIORITIES;
    }

    public function getStatusUrl(): string
    {
        return $this->getUrl('leads/lead/status', ['id' => (int) $this->getLead()->getId()]);
    }

    public function getDeleteUrl(): string
    {
        return $this->getUrl('leads/lead/delete', ['id' => (int) $this->getLead()->getId()]);
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('leads');
    }

    /**
     * Fields worth showing, with empty ones dropped so the screen shows what is
     * known rather than a column of dashes.
     *
     * @return array<string, string>
     */
    public function getDetailRows(): array
    {
        $lead = $this->getLead();

        $rows = [
            'Enquiry type'    => $lead->getTypeLabel(),
            'Name'            => $lead->getName(),
            'Phone'           => $lead->getPhone(),
            'Email'           => $lead->getEmail(),
            'City'            => $lead->getCity(),
            'PIN code'        => (string) $lead->getData('pincode'),
            'State'           => (string) $lead->getData('state'),
            'Monthly bill'    => $lead->getBillRangeLabel(),
            'Bill amount'     => $lead->getData('monthly_bill_amount') !== null
                                    ? $this->formatCurrency((float) $lead->getData('monthly_bill_amount'))
                                    : '',
            'Roof type'       => $lead->getRoofTypeLabel(),
            'Roof area'       => $lead->getData('roof_area_sqft') ? $lead->getData('roof_area_sqft') . ' sq ft' : '',
        ];

        if ($lead->isSociety()) {
            $rows += [
                'Society name'  => (string) $lead->getData('society_name'),
                'Their role'    => Lead::SOCIETY_DESIGNATIONS[(string) $lead->getData('society_designation')] ?? '',
                'AGM status'    => Lead::AGM_STATUSES[(string) $lead->getData('agm_status')] ?? '',
                'Total flats'   => (string) $lead->getData('total_flats'),
            ];
        }

        if ($lead->isCommercial()) {
            $rows += [
                'Business name' => (string) $lead->getData('company_name'),
                'GSTIN'         => (string) $lead->getData('gstin'),
            ];
        }

        $rows += [
            'Message'   => (string) $lead->getData('message'),
            'Source'    => (string) $lead->getData('source_page'),
            'Campaign'  => trim(implode(' / ', array_filter([
                                (string) $lead->getData('utm_source'),
                                (string) $lead->getData('utm_medium'),
                                (string) $lead->getData('utm_campaign'),
                            ]))),
            'Received'  => $this->formatDate((string) $lead->getData('created_at'), 'd M Y, H:i'),
            'IP address' => (string) $lead->getData('ip_address'),
        ];

        return array_filter($rows, static fn($value) => trim((string) $value) !== '');
    }
}
