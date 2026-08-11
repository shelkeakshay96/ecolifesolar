<?php

declare(strict_types=1);

namespace EcoLife\Testimonial\Controller\Adminhtml\Testimonial;

use EcoLife\Backend\App\Action\AbstractAction;
use EcoLife\Core\App\Logger;
use EcoLife\Core\Controller\ResultInterface;
use EcoLife\Testimonial\Model\Testimonial;
use Throwable;

/** POST only, like every other destructive action. */
final class Delete extends AbstractAction
{
    protected function execute(): ResultInterface
    {
        $messages = $this->context->getMessages();
        $back     = $this->context->getUrl()->getUrl('testimonials');

        if (!$this->getRequest()->isPost()) {
            $messages->error('That action has to be a POST.');
            return $this->resultRedirect()->setUrl($back);
        }

        $testimonial = (new Testimonial())->load((int) $this->getRequest()->getParam('id', 0));

        if ($testimonial->getId() === null) {
            $messages->error('That testimonial no longer exists.');
            return $this->resultRedirect()->setUrl($back);
        }

        try {
            $testimonial->delete();
        } catch (Throwable $e) {
            Logger::exception($e);
            $messages->error('Could not remove that testimonial.');
            return $this->resultRedirect()->setUrl($back);
        }

        Logger::info('Testimonial deleted', [
            'name' => $testimonial->getCustomerName(),
            'by'   => $this->getCurrentUser()?->getUsername(),
        ]);

        $messages->success('Testimonial removed.');

        return $this->resultRedirect()->setUrl($back);
    }
}
