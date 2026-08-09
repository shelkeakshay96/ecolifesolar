<?php

declare(strict_types=1);

namespace EcoLife\Gallery\Controller\Adminhtml\Index;

use EcoLife\Backend\App\Action\AbstractAction;
use EcoLife\Core\Controller\ResultInterface;
use EcoLife\Gallery\Block\Adminhtml\Manage;

/**
 * Gallery management: the list, the upload form and the delete buttons on one
 * screen. The family will use this a handful of times a month, and a separate
 * "add" page would be one more thing to explain.
 */
final class Index extends AbstractAction
{
    protected function execute(): ResultInterface
    {
        return $this->resultPage()
            ->setTitle('Gallery | EcoLifeSolar admin')
            ->setBodyClass('page-admin-gallery')
            ->setContent(Manage::class, 'EcoLife_Gallery::manage.phtml');
    }
}
