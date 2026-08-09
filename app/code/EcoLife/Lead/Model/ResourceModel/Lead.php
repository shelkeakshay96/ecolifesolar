<?php

declare(strict_types=1);

namespace EcoLife\Lead\Model\ResourceModel;

use EcoLife\Core\Model\ResourceModel\AbstractResource;

/**
 * `lead` is a reserved word in MySQL 8. AbstractResource quotes every
 * identifier unconditionally, so that costs nothing here -- but any
 * hand-written SQL against this table must backtick it.
 */
final class Lead extends AbstractResource
{
    protected string $table = 'lead';
    protected string $idFieldName = 'lead_id';

    protected array $fields = [
        'lead_id', 'lead_type',
        'name', 'email', 'phone', 'city', 'pincode', 'state',
        'monthly_bill_range', 'monthly_bill_amount', 'roof_type', 'roof_area_sqft',
        'society_name', 'society_designation', 'agm_status', 'total_flats',
        'company_name', 'gstin',
        'message',
        'status', 'priority', 'assigned_to', 'admin_notes', 'contacted_at', 'next_followup_at',
        'has_quotation', 'has_invoice',
        'source_page', 'utm_source', 'utm_medium', 'utm_campaign', 'ip_address', 'user_agent',
        'created_at', 'updated_at',
    ];
}
