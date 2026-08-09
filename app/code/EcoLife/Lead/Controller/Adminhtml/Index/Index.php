<?php

declare(strict_types=1);

namespace EcoLife\Lead\Controller\Adminhtml\Index;

use EcoLife\Backend\App\Action\AbstractAction;
use EcoLife\Core\Controller\ResultInterface;
use EcoLife\Lead\Block\Adminhtml\Grid;

/**
 * The lead grid. Lives in EcoLife_Lead, not EcoLife_Backend: adding a field to
 * a lead should touch one directory, and switching the module off should take
 * the public form and these admin screens away together.
 */
final class Index extends AbstractAction
{
    protected function execute(): ResultInterface
    {
        return $this->resultPage()
            ->setTitle('Enquiries | EcoLifeSolar admin')
            ->setBodyClass('page-admin-leads')
            ->setContent(Grid::class, 'EcoLife_Lead::grid.phtml');
    }
}
