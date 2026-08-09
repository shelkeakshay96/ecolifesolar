<?php

declare(strict_types=1);

namespace EcoLife\Lead\Controller\Adminhtml\Lead;

use EcoLife\Backend\App\Action\AbstractAction;
use EcoLife\Core\Controller\ResultInterface;
use EcoLife\Lead\Block\Adminhtml\Grid;
use EcoLife\Lead\Model\Lead;
use EcoLife\Lead\Model\ResourceModel\Lead\Collection;

/**
 * CSV export of the current grid selection.
 *
 * Exports what the grid is showing, filters included, so "send me last month's
 * Karad enquiries" is the same URL with a different extension.
 */
final class Export extends AbstractAction
{
    private const COLUMNS = [
        'lead_id'            => 'ID',
        'created_at'         => 'Received',
        'lead_type'          => 'Type',
        'name'               => 'Name',
        'phone'              => 'Phone',
        'email'              => 'Email',
        'city'               => 'City',
        'pincode'            => 'PIN',
        'monthly_bill_range' => 'Monthly bill',
        'roof_type'          => 'Roof',
        'roof_area_sqft'     => 'Roof area (sq ft)',
        'society_name'       => 'Society',
        'company_name'       => 'Business',
        'gstin'              => 'GSTIN',
        'status'             => 'Status',
        'priority'           => 'Priority',
        'next_followup_at'   => 'Next follow-up',
        'admin_notes'        => 'Notes',
        'message'            => 'Message',
        'source_page'        => 'Source',
    ];

    protected function execute(): ResultInterface
    {
        $filters = (new Grid($this->context))->getFilters();

        $collection = (new Collection())
            ->applyGridFilters($filters)
            ->addOrder('created_at', Collection::SORT_DESC);

        $handle = fopen('php://temp', 'r+');

        // UTF-8 BOM, so Excel on Windows opens the file with rupee signs and
        // Marathi names intact instead of mojibake.
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, array_values(self::COLUMNS));

        /** @var Lead $lead */
        foreach ($collection as $lead) {
            $row = [];
            foreach (array_keys(self::COLUMNS) as $column) {
                $row[] = $this->cell($lead, $column);
            }
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $this->resultRaw()
            ->setContentType('text/csv; charset=UTF-8')
            ->setDownload('ecolife-enquiries-' . date('Y-m-d') . '.csv')
            ->setContents($csv);
    }

    private function cell(Lead $lead, string $column): string
    {
        $value = (string) ($lead->getData($column) ?? '');

        $value = match ($column) {
            'status'             => $lead->getStatusLabel(),
            'lead_type'          => $lead->getTypeLabel(),
            'roof_type'          => $lead->getRoofTypeLabel(),
            'monthly_bill_range' => $lead->getBillRangeLabel(),
            default              => $value,
        };

        // A cell starting with = + - or @ is executed as a formula by Excel and
        // Sheets. Prefix with a quote so an exported enquiry cannot become a
        // spreadsheet payload on the family's machine.
        if ($value !== '' && str_contains("=+-@\t\r", $value[0])) {
            $value = "'" . $value;
        }

        return $value;
    }
}
