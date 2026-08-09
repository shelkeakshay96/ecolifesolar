<?php

declare(strict_types=1);

namespace EcoLife\Lead\Controller\Adminhtml\Lead;

use EcoLife\Backend\App\Action\AbstractAction;
use EcoLife\Core\App\Logger;
use EcoLife\Core\Controller\ResultInterface;
use EcoLife\Lead\Model\Lead;
use Throwable;

/**
 * Delete a lead. POST only -- a destructive action behind a GET is one crawler
 * away from emptying the table, and CSRF protection only applies to POST.
 */
final class Delete extends AbstractAction
{
    protected function execute(): ResultInterface
    {
        $messages = $this->context->getMessages();
        $gridUrl  = $this->context->getUrl()->getUrl('leads');

        if (!$this->getRequest()->isPost()) {
            $messages->error('Delete must be submitted from the enquiry screen.');
            return $this->resultRedirect()->setUrl($gridUrl);
        }

        $id   = (int) $this->getRequest()->getParam('id', 0);
        $lead = (new Lead())->load($id);

        if ($lead->getId() === null) {
            $messages->error('That enquiry no longer exists.');
            return $this->resultRedirect()->setUrl($gridUrl);
        }

        $name = $lead->getName();

        try {
            // lead_status_history has ON DELETE CASCADE, so its rows go too.
            $lead->delete();
            Logger::info('Lead deleted', [
                'lead_id' => $id,
                'by'      => $this->getCurrentUser()?->getUsername(),
            ]);
            $messages->success(sprintf('Enquiry from %s deleted.', $name));
        } catch (Throwable $e) {
            Logger::exception($e);
            $messages->error('Could not delete that enquiry.');
        }

        return $this->resultRedirect()->setUrl($gridUrl);
    }
}
