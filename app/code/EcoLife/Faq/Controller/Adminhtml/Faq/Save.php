<?php

declare(strict_types=1);

namespace EcoLife\Faq\Controller\Adminhtml\Faq;

use EcoLife\Backend\App\Action\AbstractAction;
use EcoLife\Core\App\Logger;
use EcoLife\Core\Controller\ResultInterface;
use EcoLife\Faq\Model\Faq;
use Throwable;

/**
 * Insert and update, one controller, as Testimonial\...\Testimonial\Save does.
 *
 * The `use` above is load bearing. This class sits inside the namespace
 * EcoLife\Faq\Controller\Adminhtml\Faq, so without the import `new Faq()`
 * resolves to this directory rather than to the model -- a fatal at runtime,
 * not a parse error.
 */
final class Save extends AbstractAction
{
    protected function execute(): ResultInterface
    {
        $messages = $this->context->getMessages();
        $back     = $this->context->getUrl()->getUrl('faqs');

        if (!$this->getRequest()->isPost()) {
            return $this->resultRedirect()->setUrl($back);
        }

        $request = $this->getRequest();
        $id      = (int) $request->getPost('faq_id', 0);
        $faq     = $id > 0 ? (new Faq())->load($id) : new Faq();

        if ($id > 0 && $faq->getId() === null) {
            $messages->error('That question no longer exists.');
            return $this->resultRedirect()->setUrl($back);
        }

        $question = trim((string) $request->getPost('question', ''));
        $answer   = trim((string) $request->getPost('answer', ''));

        if ($question === '') {
            $messages->error('Please enter the question.');
            return $this->resultRedirect()->setUrl($back);
        }

        if ($answer === '') {
            $messages->error('Please enter the answer.');
            return $this->resultRedirect()->setUrl($back);
        }

        $linkUrl   = $this->internalPath((string) $request->getPost('link_url', ''));
        $linkLabel = trim((string) $request->getPost('link_label', ''));

        if ($linkUrl === false) {
            $messages->error('The link has to be a path on this site, starting with a single "/" -- for example /calculator.');
            return $this->resultRedirect()->setUrl($back);
        }

        // Both halves or neither. A URL with no label renders nothing, and a
        // label with no URL renders an anchor that goes nowhere; either way the
        // admin thinks they added a link and the page disagrees.
        if (($linkUrl === null) !== ($linkLabel === '')) {
            $messages->error('A link needs both an address and a label, or neither.');
            return $this->resultRedirect()->setUrl($back);
        }

        $faq->addData([
            'question'   => mb_substr($question, 0, 255),
            'answer'     => mb_substr($answer, 0, 65535),
            'link_url'   => $linkUrl,
            'link_label' => $linkLabel !== '' ? mb_substr($linkLabel, 0, 120) : null,
            'sort_order' => (int) $request->getPost('sort_order', 0),
            'is_active'  => $request->getPost('is_active') ? 1 : 0,
        ]);

        try {
            $faq->save();
        } catch (Throwable $e) {
            Logger::exception($e);
            $messages->error('Could not save that question.');
            return $this->resultRedirect()->setUrl($back);
        }

        Logger::info('FAQ saved', [
            'id' => (int) $faq->getId(),
            'by' => $this->getCurrentUser()?->getUsername(),
        ]);

        $messages->success($id > 0 ? 'Question updated.' : 'Question added.');

        return $this->resultRedirect()->setUrl($back);
    }

    /**
     * A path on this site, null when blank, or false when it is neither.
     *
     * Restricted to internal paths deliberately. This field ends up as an href
     * on a public page, and the two things that must not get through are an
     * absolute URL to somewhere else and a protocol-relative "//evil.test",
     * which browsers treat as absolute despite the leading slash. Only an admin
     * can reach this form, so this is a guard against a mistake rather than an
     * attacker -- but a link the family cannot explain is still a link on their
     * site.
     */
    private function internalPath(string $value): string|false|null
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (!str_starts_with($value, '/') || str_starts_with($value, '//')) {
            return false;
        }

        return mb_substr($value, 0, 255);
    }
}
