<?php

declare(strict_types=1);

namespace EcoLife\Backend\App\Action;

use EcoLife\Backend\Model\Auth;
use EcoLife\Backend\Model\AdminUser;
use EcoLife\Core\App\Context;
use EcoLife\Core\Controller\AbstractAction as CoreAction;
use EcoLife\Core\Controller\AuthEnforcedInterface;
use EcoLife\Core\Controller\ResultInterface;

/**
 * Base for every admin controller. This is the first of the three layers
 * protecting the admin panel.
 *
 * dispatch() is FINAL and checks isLoggedIn() unless the subclass sets
 * ALLOW_GUEST. The default is false, which means a new admin controller is
 * protected by writing nothing at all -- the safe thing is what happens when
 * the author does nothing, rather than something they have to remember.
 *
 * Implementing AuthEnforcedInterface is what satisfies the FrontController
 * invariant. Because dispatch() here is final and this is the only class that
 * implements the interface, the only way to be dispatchable in adminhtml is to
 * inherit the real check.
 */
abstract class AbstractAction extends CoreAction implements AuthEnforcedInterface
{
    /** Only the login screen and its POST handler may set this. */
    public const ALLOW_GUEST = false;

    protected Auth $auth;

    public function __construct(Context $context)
    {
        parent::__construct($context);
        $this->auth = new Auth($context);
    }

    /**
     * FINAL. This is the login check, and no admin controller can replace it.
     * Core's dispatch() calls this before anything else and is itself final,
     * so the only lever a subclass has is ALLOW_GUEST -- a const, greppable in
     * one command across the whole codebase.
     */
    final protected function authenticate(): ?ResultInterface
    {
        if (static::ALLOW_GUEST || $this->auth->isLoggedIn()) {
            return null;
        }

        return $this->requireLogin();
    }

    private function requireLogin(): ResultInterface
    {
        if ($this->getRequest()->isAjax()) {
            return $this->resultJson()->setError('Your session has ended. Please sign in again.', [], 401);
        }

        $this->context->getMessages()->notice('Please sign in to continue.');

        return $this->resultRedirect()->setUrl($this->context->getUrl()->getUrl('auth/login'));
    }

    protected function getCurrentUser(): ?AdminUser
    {
        return $this->auth->getUser();
    }

    /** Admin pages must never be indexed, whatever else they set. */
    protected function resultPage(): \EcoLife\Core\View\Result\Page
    {
        return parent::resultPage()->setRobots('noindex,nofollow');
    }
}
