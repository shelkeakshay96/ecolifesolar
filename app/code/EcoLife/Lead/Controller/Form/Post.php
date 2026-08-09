<?php

declare(strict_types=1);

namespace EcoLife\Lead\Controller\Form;

use EcoLife\Core\App\Logger;
use EcoLife\Core\Controller\AbstractAction;
use EcoLife\Core\Controller\ResultInterface;
use EcoLife\Lead\Model\Lead;
use EcoLife\Lead\Model\SpamGuard;
use EcoLife\Lead\Model\StatusHistory;
use EcoLife\Lead\Model\Validator;
use Throwable;

/**
 * Receives an enquiry.
 *
 * The ordering here is the whole point of the module: the lead is saved first,
 * and only then does anything else happen. Notification, history and every
 * other side effect run inside their own try/catch, because a lead that was
 * captured and not emailed is a recoverable problem, while a lead that was
 * never written down is the exact failure this rebuild exists to fix.
 */
final class Post extends AbstractAction
{
    protected function execute(): ResultInterface
    {
        $request = $this->getRequest();

        if (!$request->isPost()) {
            return $this->resultRedirect()->setUrl('/contact');
        }

        $guard = new SpamGuard($this->context);

        // Honeypot: answer exactly as if it worked, and write nothing.
        if ($guard->honeypotTripped()) {
            Logger::info('Lead honeypot tripped', ['ip' => $request->getClientIp()]);
            return $this->success('Thank you. We will call you shortly.');
        }

        if ($reason = $guard->rejectionReason()) {
            return $this->failure($reason);
        }

        $validator = new Validator();

        if (!$validator->validate((array) $request->getPost())) {
            return $this->failure(
                'Please check the highlighted fields.',
                $validator->getErrors()
            );
        }

        try {
            $lead = $this->persist($validator->getCleanData());
        } catch (Throwable $e) {
            Logger::exception($e);
            return $this->failure(
                'Something went wrong saving your enquiry. Please call us and we will help straight away.'
            );
        }

        $this->runSideEffects($lead);

        return $this->success(
            'Thank you. Your enquiry has reached us and we will call you shortly.',
            ['lead_id' => (int) $lead->getId()]
        );
    }

    /** @param array<string, mixed> $data */
    private function persist(array $data): Lead
    {
        $request = $this->getRequest();

        $lead = new Lead();
        $lead->addData($data)->addData([
            'status'      => 'new',
            'priority'    => 'medium',
            'source_page' => $this->allowedSource((string) $request->getPost('source_page', '')),
            'utm_source'  => $this->utm('utm_source'),
            'utm_medium'  => $this->utm('utm_medium'),
            'utm_campaign' => $this->utm('utm_campaign'),
            'ip_address'  => $request->getClientIp(),
            'user_agent'  => $request->getUserAgent(),
        ]);

        $lead->save();

        return $lead;
    }

    /**
     * Everything that is not the lead itself. Each failure is logged and
     * swallowed: the customer has already been told we have their details, and
     * that statement must remain true.
     */
    private function runSideEffects(Lead $lead): void
    {
        try {
            StatusHistory::record((int) $lead->getId(), null, 'new', null, 'Enquiry received from the website');
        } catch (Throwable $e) {
            Logger::error('Could not write initial lead history: ' . $e->getMessage());
        }

        try {
            $this->notify($lead);
        } catch (Throwable $e) {
            Logger::error('Lead notification failed: ' . $e->getMessage());
        }

        Logger::info('Lead captured', [
            'lead_id' => $lead->getId(),
            'type'    => $lead->getLeadType(),
            'city'    => $lead->getCity(),
        ]);
    }

    /**
     * Hands off to EcoLife_Mail when it is present. Referenced by string so
     * that Lead keeps working when Mail is switched off in app/etc/config.php.
     */
    private function notify(Lead $lead): void
    {
        $notifier = '\\EcoLife\\Mail\\Model\\LeadNotification';

        if (!class_exists($notifier)) {
            Logger::info('EcoLife_Mail not available; notification skipped', ['lead_id' => $lead->getId()]);
            return;
        }

        (new $notifier($this->context))->send($lead);
    }

    private function allowedSource(string $source): string
    {
        return in_array($source, ['home', 'contact', 'services', 'calculator'], true) ? $source : 'home';
    }

    private function utm(string $key): ?string
    {
        $value = trim((string) $this->getRequest()->getPost($key, ''));
        return $value === '' ? null : mb_substr($value, 0, 100);
    }

    /** @param array<string, mixed> $extra */
    private function success(string $message, array $extra = []): ResultInterface
    {
        if ($this->getRequest()->isAjax()) {
            return $this->resultJson()->setSuccess($message, $extra);
        }

        $this->context->getMessages()->success($message);

        return $this->resultRedirect()->setUrl('/contact');
    }

    /** @param array<string, string> $errors */
    private function failure(string $message, array $errors = []): ResultInterface
    {
        if ($this->getRequest()->isAjax()) {
            return $this->resultJson()->setError($message, $errors);
        }

        $this->context->getMessages()->error($message);

        return $this->resultRedirect()->setUrl('/contact');
    }
}
