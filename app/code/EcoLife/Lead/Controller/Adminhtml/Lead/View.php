<?php

declare(strict_types=1);

namespace EcoLife\Lead\Controller\Adminhtml\Lead;

use EcoLife\Backend\App\Action\AbstractAction;
use EcoLife\Core\Controller\ResultInterface;
use EcoLife\Core\Exception\NotFoundException;
use EcoLife\Lead\Block\Adminhtml\View as ViewBlock;
use EcoLife\Lead\Model\Lead;

final class View extends AbstractAction
{
    protected function execute(): ResultInterface
    {
        $lead = $this->loadLead();

        return $this->resultPage()
            ->setTitle($lead->getName() . ' | Enquiry | EcoLifeSolar admin')
            ->setBodyClass('page-admin-lead-view')
            ->setContent(ViewBlock::class, 'EcoLife_Lead::view.phtml', ['lead' => $lead]);
    }

    private function loadLead(): Lead
    {
        $id = (int) $this->getRequest()->getParam('id', 0);

        if ($id <= 0) {
            throw new NotFoundException('No lead id given');
        }

        $lead = (new Lead())->load($id);

        if ($lead->getId() === null) {
            throw new NotFoundException("Lead {$id} does not exist");
        }

        return $lead;
    }
}
