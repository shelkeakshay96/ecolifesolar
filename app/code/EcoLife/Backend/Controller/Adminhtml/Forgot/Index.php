<?php

declare(strict_types=1);

namespace EcoLife\Backend\Controller\Adminhtml\Forgot;

use EcoLife\Backend\App\Action\AbstractAction;
use EcoLife\Backend\Model\AdminUser;
use EcoLife\Backend\Model\PasswordReset;
use EcoLife\Core\App\Logger;
use EcoLife\Core\Controller\ResultInterface;
use EcoLife\Core\View\Element\Template;
use EcoLife\Mail\Model\PasswordResetNotification;
use Throwable;

/**
 * "Send me a reset link."
 *
 * ALLOW_GUEST, necessarily: somebody who cannot sign in is the only person who
 * will ever reach it.
 *
 * Every path through this controller ends in the same message and the same
 * redirect, whether the address belongs to an account or not, whether that
 * account is active, and whether the throttle silently declined to send. A form
 * that answers "no such user" is a form that will happily confirm which of a
 * leaked address list can sign in here.
 */
final class Index extends AbstractAction
{
    public const ALLOW_GUEST = true;

    private const NEUTRAL = 'If that email address belongs to an admin account, a reset link is '
                          . 'on its way. It stops working in an hour.';

    protected function execute(): ResultInterface
    {
        if ($this->auth->isLoggedIn()) {
            return $this->resultRedirect()->setUrl($this->context->getUrl()->getUrl());
        }

        if ($this->getRequest()->isPost()) {
            return $this->request();
        }

        return $this->resultPage()
            ->setTitle('Reset your password | EcoLifeSolar admin')
            ->setBodyClass('page-admin-login')
            ->setContent(Template::class, 'EcoLife_Backend::forgot.phtml');
    }

    private function request(): ResultInterface
    {
        $email = trim((string) $this->getRequest()->getPost('email', ''));
        $login = $this->context->getUrl()->getUrl('auth/login');

        // The only branch that answers differently, and it says nothing about
        // whether an account exists -- an empty box is an empty box.
        if ($email === '') {
            $this->context->getMessages()->error('Please enter your email address.');
            return $this->resultRedirect()->setUrl($this->context->getUrl()->getUrl('auth/forgot'));
        }

        try {
            $this->dispatchLink($email);
        } catch (Throwable $e) {
            // A failure to send is logged and swallowed. Surfacing it would
            // leak that the address matched something worth trying to email.
            Logger::exception($e);
        }

        $this->context->getMessages()->success(self::NEUTRAL);

        return $this->resultRedirect()->setUrl($login);
    }

    /** Does the real work, and tells the caller nothing about what it found. */
    private function dispatchLink(string $email): void
    {
        $user = (new AdminUser())->loadByEmail($email);
        $ip   = $this->context->getRequest()->getClientIp();

        if ($user->getId() === null) {
            Logger::warning('Password reset requested for unknown address', ['ip' => $ip]);
            return;
        }

        if (!$user->isActive()) {
            Logger::warning('Password reset requested for inactive account', [
                'user_id' => (int) $user->getId(), 'ip' => $ip,
            ]);
            return;
        }

        $token = PasswordReset::issue((int) $user->getId(), $ip);

        if ($token === null) {
            return;   // Throttled. Already logged inside issue().
        }

        // _query, not a plain param. A plain param becomes /auth/reset/token/<value>,
        // and the standard router reads segment 2 as the ACTION -- so the link would
        // resolve to a Reset\Token controller that does not exist and 404. The token
        // has to arrive as a query string.
        //
        // Absolute, because this is going into a mail client that has no notion of
        // the site it came from.
        $url = $this->context->getUrl()->getUrl('auth/reset', ['_query' => ['token' => $token]], true);

        (new PasswordResetNotification($this->context))->send(
            $user->getEmail(),
            $user->getDisplayName(),
            $url,
            PasswordReset::VALID_MINUTES
        );
    }
}
