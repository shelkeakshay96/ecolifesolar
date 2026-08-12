<?php

declare(strict_types=1);

namespace EcoLife\Backend\Controller\Adminhtml\Reset;

use EcoLife\Backend\App\Action\AbstractAction;
use EcoLife\Backend\Model\PasswordReset;
use EcoLife\Core\App\Logger;
use EcoLife\Core\Controller\ResultInterface;
use EcoLife\Core\View\Element\Template;
use Throwable;

/**
 * "Here is my new password."
 *
 * ALLOW_GUEST for the same reason as Forgot. What stands in for authentication
 * is the token, which is 32 bytes of random_bytes stored only as a sha256, good
 * for an hour, and spent on first use.
 *
 * The token is re-checked on the POST rather than trusted from the hidden
 * field, because a form rendered an hour ago is a form whose token may have
 * expired or already been used since.
 */
final class Index extends AbstractAction
{
    public const ALLOW_GUEST = true;

    /** Matches the floor in bin/ecolife admin:user:create. One rule, both doors. */
    private const MIN_LENGTH = 12;

    protected function execute(): ResultInterface
    {
        $login = $this->context->getUrl()->getUrl('auth/login');
        $token = (string) $this->getRequest()->getParam('token', '');

        if ($this->getRequest()->isPost()) {
            $token = (string) $this->getRequest()->getPost('token', '');
        }

        $reset = PasswordReset::findValid($token);

        if ($reset === null) {
            // Expired, already used, or never real: one message for all three.
            // Which of them applies is not something the holder of a bad link
            // has any business learning.
            $this->context->getMessages()->error(
                'That reset link is no longer valid. Links last an hour and work once. '
                . 'Please request a new one.'
            );
            return $this->resultRedirect()->setUrl($this->context->getUrl()->getUrl('auth/forgot'));
        }

        if ($this->getRequest()->isPost()) {
            return $this->apply($reset, $login);
        }

        return $this->resultPage()
            ->setTitle('Choose a new password | EcoLifeSolar admin')
            ->setBodyClass('page-admin-login')
            ->setContent(Template::class, 'EcoLife_Backend::reset.phtml', ['token' => $token]);
    }

    private function apply(PasswordReset $reset, string $login): ResultInterface
    {
        $messages = $this->context->getMessages();
        $password = (string) $this->getRequest()->getPost('password', '');
        $confirm  = (string) $this->getRequest()->getPost('password_confirm', '');
        // _query for the same reason as in Forgot: a path param would be read as
        // the action segment and never reach this controller.
        $back     = $this->context->getUrl()->getUrl('auth/reset', [
            '_query' => ['token' => (string) $this->getRequest()->getPost('token', '')],
        ]);

        if (strlen($password) < self::MIN_LENGTH) {
            $messages->error(sprintf('Please use at least %d characters.', self::MIN_LENGTH));
            return $this->resultRedirect()->setUrl($back);
        }

        if ($password !== $confirm) {
            $messages->error('The two passwords do not match.');
            return $this->resultRedirect()->setUrl($back);
        }

        $user = $reset->getUser();

        if ($user->getId() === null || !$user->isActive()) {
            $messages->error('That account is no longer available.');
            return $this->resultRedirect()->setUrl($login);
        }

        try {
            // Lockout cleared in the same write: somebody who has just proved
            // control of the mailbox has answered what the lockout was asking.
            $user->setPassword($password)->clearLockout()->save();

            // Only after the password is safely changed. The other order can
            // spend the token and then fail to change anything, which leaves
            // the user with a dead link and the old password.
            $reset->consume();
        } catch (Throwable $e) {
            Logger::exception($e);
            $messages->error('Could not set that password. Please try the link again.');
            return $this->resultRedirect()->setUrl($back);
        }

        // Any session that was already signed in as this user is now signed in
        // on a password that no longer exists. Ending it is the point of
        // resetting after a compromise.
        $this->auth->logout();

        Logger::info('Password reset completed', [
            'user_id' => (int) $user->getId(),
            'ip'      => $this->context->getRequest()->getClientIp(),
        ]);

        $messages->success('Your password has been changed. Please sign in with it.');

        return $this->resultRedirect()->setUrl($login);
    }
}
