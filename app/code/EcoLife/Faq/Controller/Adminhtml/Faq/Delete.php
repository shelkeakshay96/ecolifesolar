<?php

declare(strict_types=1);

namespace EcoLife\Faq\Controller\Adminhtml\Faq;

use EcoLife\Backend\App\Action\AbstractAction;
use EcoLife\Core\App\Logger;
use EcoLife\Core\Controller\ResultInterface;
use EcoLife\Faq\Model\Faq;
use Throwable;

/** POST only, like every other destructive action. */
final class Delete extends AbstractAction
{
    protected function execute(): ResultInterface
    {
        $messages = $this->context->getMessages();
        $back     = $this->context->getUrl()->getUrl('faqs');

        if (!$this->getRequest()->isPost()) {
            $messages->error('That action has to be a POST.');
            return $this->resultRedirect()->setUrl($back);
        }

        $faq = (new Faq())->load((int) $this->getRequest()->getParam('id', 0));

        if ($faq->getId() === null) {
            $messages->error('That question no longer exists.');
            return $this->resultRedirect()->setUrl($back);
        }

        $question = $faq->getQuestion();

        try {
            $faq->delete();
        } catch (Throwable $e) {
            Logger::exception($e);
            $messages->error('Could not remove that question.');
            return $this->resultRedirect()->setUrl($back);
        }

        Logger::info('FAQ deleted', [
            'question' => $question,
            'by'       => $this->getCurrentUser()?->getUsername(),
        ]);

        $messages->success('Question removed.');

        return $this->resultRedirect()->setUrl($back);
    }
}
