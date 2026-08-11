<?php

declare(strict_types=1);

namespace EcoLife\Testimonial\Controller\Adminhtml\Index;

use EcoLife\Backend\App\Action\AbstractAction;
use EcoLife\Core\Controller\ResultInterface;
use EcoLife\Testimonial\Block\Adminhtml\Manage;

/**
 * Testimonial management: the list and the add form on one screen, as the
 * gallery does. The family will type a quote in maybe once a month, and a
 * separate "add" page would be one more thing to explain.
 *
 * This lives under Index/ rather than Testimonial/ because the Standard router
 * defaults the controller and action segments to "index", so /admin/testimonials
 * resolves here and nowhere else.
 */
final class Index extends AbstractAction
{
    protected function execute(): ResultInterface
    {
        return $this->resultPage()
            ->setTitle('Testimonials | EcoLifeSolar admin')
            ->setBodyClass('page-admin-testimonials')
            ->setContent(Manage::class, 'EcoLife_Testimonial::manage.phtml');
    }
}
