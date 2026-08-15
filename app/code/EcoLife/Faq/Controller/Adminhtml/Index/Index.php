<?php

declare(strict_types=1);

namespace EcoLife\Faq\Controller\Adminhtml\Index;

use EcoLife\Backend\App\Action\AbstractAction;
use EcoLife\Core\Controller\ResultInterface;
use EcoLife\Faq\Block\Adminhtml\Manage;

/**
 * FAQ management: the list and the add form on one screen, as the gallery and
 * testimonial screens do.
 *
 * This lives under Index/ rather than Faq/ because the Standard router defaults
 * the controller and action segments to "index", so /admin/faqs resolves here
 * and nowhere else.
 */
final class Index extends AbstractAction
{
    protected function execute(): ResultInterface
    {
        return $this->resultPage()
            ->setTitle('FAQ | EcoLifeSolar admin')
            ->setBodyClass('page-admin-faqs')
            ->setContent(Manage::class, 'EcoLife_Faq::manage.phtml');
    }
}
