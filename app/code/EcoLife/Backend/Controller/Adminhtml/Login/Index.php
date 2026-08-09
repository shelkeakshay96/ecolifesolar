<?php

declare(strict_types=1);

namespace EcoLife\Backend\Controller\Adminhtml\Login;

use EcoLife\Backend\App\Action\AbstractAction;
use EcoLife\Backend\Block\Adminhtml\Login as LoginBlock;
use EcoLife\Core\Controller\ResultInterface;

/**
 * The sign-in screen. One of only two controllers in the application permitted
 * to set ALLOW_GUEST.
 */
final class Index extends AbstractAction
{
    public const ALLOW_GUEST = true;

    protected function execute(): ResultInterface
    {
        // Already signed in: no reason to show the form again.
        if ($this->auth->isLoggedIn()) {
            return $this->resultRedirect()->setUrl($this->context->getUrl()->getUrl());
        }

        if ($this->getRequest()->isPost()) {
            return $this->attemptLogin();
        }

        return $this->resultPage()
            ->setTitle('Sign in | EcoLifeSolar admin')
            ->setBodyClass('page-admin-login')
            ->setContent(LoginBlock::class, 'EcoLife_Backend::login.phtml');
    }

    private function attemptLogin(): ResultInterface
    {
        $username = trim((string) $this->getRequest()->getPost('username', ''));
        $password = (string) $this->getRequest()->getPost('password', '');

        if ($username === '' || $password === '') {
            $this->context->getMessages()->error('Please enter both a username and a password.');
            return $this->resultRedirect()->setUrl($this->context->getUrl()->getUrl('auth/login'));
        }

        [$success, $message] = $this->auth->login($username, $password);

        if (!$success) {
            $this->context->getMessages()->error($message);
            return $this->resultRedirect()->setUrl($this->context->getUrl()->getUrl('auth/login'));
        }

        $this->context->getMessages()->success('Signed in.');

        return $this->resultRedirect()->setUrl($this->context->getUrl()->getUrl());
    }
}
