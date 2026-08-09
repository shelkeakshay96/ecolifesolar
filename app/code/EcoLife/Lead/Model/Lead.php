<?php

declare(strict_types=1);

namespace EcoLife\Lead\Model;

use EcoLife\Core\Model\AbstractModel;
use EcoLife\Core\Model\ResourceModel\AbstractResource;
use EcoLife\Lead\Model\ResourceModel\Lead as LeadResource;

/**
 * A customer enquiry. The reason the site exists.
 *
 * The ENUM whitelists are declared here rather than read from the schema
 * because they are also the validator's whitelist: a tampered lead_type posted
 * from devtools is rejected against this array, and the same array renders the
 * form's select options. One source, so the two cannot drift.
 */
final class Lead extends AbstractModel
{
    public const TYPES = ['residential', 'society', 'commercial'];

    public const STATUSES = [
        'new'              => 'New',
        'contacted'        => 'Contacted',
        'survey_scheduled' => 'Survey scheduled',
        'survey_done'      => 'Survey done',
        'quotation_sent'   => 'Quotation sent',
        'negotiating'      => 'Negotiating',
        'converted'        => 'Converted',
        'not_interested'   => 'Not interested',
        'junk'             => 'Junk',
    ];

    public const ROOF_TYPES = [
        'rcc'          => 'RCC / concrete',
        'metal_sheet'  => 'Metal sheet',
        'tiled'        => 'Tiled',
        'other'        => 'Other',
    ];

    public const BILL_RANGES = [
        '0-1500'      => 'Under ₹1,500',
        '1500-2500'   => '₹1,500 to ₹2,500',
        '2500-5000'   => '₹2,500 to ₹5,000',
        '5000-10000'  => '₹5,000 to ₹10,000',
        '10000+'      => 'Over ₹10,000',
    ];

    public const SOCIETY_DESIGNATIONS = [
        'committee'        => 'Committee member',
        'resident'         => 'Resident',
        'builder'          => 'Builder / developer',
        'facility_manager' => 'Facility manager',
    ];

    public const AGM_STATUSES = [
        'approved'      => 'Approved at AGM',
        'in_discussion' => 'Under discussion',
        'not_started'   => 'Not raised yet',
    ];

    public const PRIORITIES = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];

    protected string $idFieldName = 'lead_id';

    public function getResource(): AbstractResource
    {
        return $this->resource ??= new LeadResource();
    }

    public function getName(): string
    {
        return (string) $this->getData('name');
    }

    public function getPhone(): string
    {
        return (string) $this->getData('phone');
    }

    public function getEmail(): string
    {
        return (string) $this->getData('email', '');
    }

    public function getCity(): string
    {
        return (string) $this->getData('city');
    }

    public function getLeadType(): string
    {
        return (string) $this->getData('lead_type', 'residential');
    }

    public function getStatus(): string
    {
        return (string) $this->getData('status', 'new');
    }

    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->getStatus()] ?? $this->getStatus();
    }

    public function getTypeLabel(): string
    {
        return ucfirst($this->getLeadType());
    }

    public function getRoofTypeLabel(): string
    {
        $roof = (string) $this->getData('roof_type', '');
        return self::ROOF_TYPES[$roof] ?? '';
    }

    public function getBillRangeLabel(): string
    {
        $range = (string) $this->getData('monthly_bill_range', '');
        return self::BILL_RANGES[$range] ?? $range;
    }

    public function isSociety(): bool
    {
        return $this->getLeadType() === 'society';
    }

    public function isCommercial(): bool
    {
        return $this->getLeadType() === 'commercial';
    }

    /** A wa.me link to this customer, for the admin detail screen. */
    public function getWhatsappLink(): string
    {
        $digits = preg_replace('/\D+/', '', $this->getPhone());
        if ($digits === '' || $digits === null) {
            return '';
        }
        return 'https://wa.me/91' . substr($digits, -10);
    }
}
