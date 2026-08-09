<?php

declare(strict_types=1);

namespace EcoLife\Lead\Controller\Adminhtml\Lead;

use EcoLife\Backend\App\Action\AbstractAction;
use EcoLife\Core\App\Logger;
use EcoLife\Core\Controller\ResultInterface;
use EcoLife\Lead\Model\Lead;
use EcoLife\Lead\Model\StatusHistory;
use Throwable;

/**
 * Update a lead's status, priority and notes.
 *
 * Every status change writes a lead_status_history row naming the acting user.
 * That record is the point: "who called this customer, and when" has to be
 * answerable without anyone's memory being involved.
 */
final class Status extends AbstractAction
{
    protected function execute(): ResultInterface
    {
        $messages = $this->context->getMessages();
        $id       = (int) $this->getRequest()->getParam('id', 0);
        $back     = $this->context->getUrl()->getUrl('leads/lead/view', ['id' => $id]);

        if (!$this->getRequest()->isPost()) {
            return $this->resultRedirect()->setUrl($back);
        }

        $lead = (new Lead())->load($id);

        if ($lead->getId() === null) {
            $messages->error('That enquiry no longer exists.');
            return $this->resultRedirect()->setUrl($this->context->getUrl()->getUrl('leads'));
        }

        $newStatus = (string) $this->getRequest()->getPost('status', '');

        if (!array_key_exists($newStatus, Lead::STATUSES)) {
            $messages->error('That is not a valid status.');
            return $this->resultRedirect()->setUrl($back);
        }

        $priority = (string) $this->getRequest()->getPost('priority', '');
        $comment  = trim((string) $this->getRequest()->getPost('comment', ''));
        $notes    = trim((string) $this->getRequest()->getPost('admin_notes', ''));
        $followup = trim((string) $this->getRequest()->getPost('next_followup_at', ''));

        $oldStatus = $lead->getStatus();

        $lead->setData('status', $newStatus);

        if (array_key_exists($priority, Lead::PRIORITIES)) {
            $lead->setData('priority', $priority);
        }

        $lead->setData('admin_notes', $notes !== '' ? mb_substr($notes, 0, 65535) : null);

        $lead->setData(
            'next_followup_at',
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $followup) ? $followup . ' 09:00:00' : null
        );

        // First time it moves off "new", record when contact actually happened.
        if ($oldStatus === 'new' && $newStatus !== 'new' && $lead->getData('contacted_at') === null) {
            $lead->setData('contacted_at', date('Y-m-d H:i:s'));
        }

        try {
            $lead->save();
        } catch (Throwable $e) {
            Logger::exception($e);
            $messages->error('Could not save that change.');
            return $this->resultRedirect()->setUrl($back);
        }

        if ($oldStatus !== $newStatus) {
            try {
                StatusHistory::record(
                    (int) $lead->getId(),
                    $oldStatus,
                    $newStatus,
                    (int) $this->getCurrentUser()?->getId(),
                    $comment !== '' ? $comment : null
                );
            } catch (Throwable $e) {
                // The lead is already updated; losing the audit line is bad but
                // not worth throwing the user's edit away over.
                Logger::error('Could not write lead history: ' . $e->getMessage());
            }
        }

        $messages->success('Enquiry updated.');

        return $this->resultRedirect()->setUrl($back);
    }
}
