<?php

declare(strict_types=1);

namespace EcoLife\Backend\Controller\Adminhtml\Index;

use EcoLife\Backend\App\Action\AbstractAction;
use EcoLife\Backend\Block\Adminhtml\Dashboard;
use EcoLife\Core\Controller\ResultInterface;

/**
 * The admin landing page. Protected by writing nothing: ALLOW_GUEST is false
 * by inheritance, so the base class's final dispatch() has already required a
 * session before execute() runs.
 */
final class Index extends AbstractAction
{
    protected function execute(): ResultInterface
    {
        return $this->resultPage()
            ->setTitle('Dashboard | EcoLifeSolar admin')
            ->setBodyClass('page-admin-dashboard')
            ->setContent(Dashboard::class, 'EcoLife_Backend::dashboard.phtml');
    }
}
