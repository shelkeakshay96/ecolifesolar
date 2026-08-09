<?php

declare(strict_types=1);

namespace EcoLife\Core\Controller;

use EcoLife\Core\App\Context;
use EcoLife\Core\App\Request;
use EcoLife\Core\App\Response;
use EcoLife\Core\Controller\Result\Json;
use EcoLife\Core\Controller\Result\Raw;
use EcoLife\Core\Controller\Result\Redirect;
use EcoLife\Core\View\Result\Page;

/**
 * Base for every controller in both areas.
 *
 * dispatch() is a template method and is final: it runs the CSRF check and then
 * calls execute(). A subclass cannot accidentally skip the check by overriding
 * dispatch(), because it cannot override dispatch().
 *
 * The only escape hatch is CSRF_EXEMPT, which is a const so that opting out is
 * visible in the class body and greppable across the codebase. Nothing in
 * Phase 1 should set it.
 */
abstract class AbstractAction
{
    /** Set true only for an endpoint that legitimately cannot carry a form key. */
    public const CSRF_EXEMPT = false;

    public function __construct(protected readonly Context $context)
    {
    }

    final public function dispatch(): ResultInterface
    {
        // Authentication first. A session that has expired should send the user
        // to the login screen, not tell them their form key is stale.
        if ($blocked = $this->authenticate()) {
            return $blocked;
        }

        $request = $this->getRequest();

        if ($request->isPost() && !static::CSRF_EXEMPT && !$this->context->getFormKey()->isValid($request)) {
            return $this->csrfFailure();
        }

        return $this->execute();
    }

    /**
     * Hook for an area that requires a signed-in user. Returning a result stops
     * dispatch before execute() runs; returning null lets it proceed.
     *
     * Core does nothing here -- the public site has no accounts.
     * Backend\App\Action\AbstractAction overrides it and declares its override
     * final, so both guarantees hold at once: no controller can skip the CSRF
     * check (dispatch is final here), and no admin controller can skip the
     * login check (the override is final there).
     */
    protected function authenticate(): ?ResultInterface
    {
        return null;
    }

    abstract protected function execute(): ResultInterface;

    /**
     * A stale or missing form key. Answered in the format the caller asked for,
     * so an AJAX submit gets JSON rather than a redirect it cannot follow.
     */
    protected function csrfFailure(): ResultInterface
    {
        if ($this->getRequest()->isAjax()) {
            return $this->resultJson()->setError(
                'Your session expired. Please reload the page and try again.',
                [],
                419
            );
        }

        $this->context->getMessages()->error('Your session expired. Please try again.');

        // Through the URL builder, not the raw path: Area::detect() has already
        // stripped the admin front name, so redirecting to getPathInfo() would
        // bounce an admin POST out to the public site.
        return $this->resultRedirect()->setUrl(
            $this->context->getUrl()->getUrl(ltrim($this->getRequest()->getPathInfo(), '/'))
        );
    }

    protected function getRequest(): Request
    {
        return $this->context->getRequest();
    }

    protected function getResponse(): Response
    {
        return $this->context->getResponse();
    }

    protected function getContext(): Context
    {
        return $this->context;
    }

    protected function resultPage(): Page
    {
        return new Page($this->context);
    }

    protected function resultRaw(): Raw
    {
        return new Raw();
    }

    protected function resultJson(): Json
    {
        return new Json();
    }

    protected function resultRedirect(): Redirect
    {
        return new Redirect();
    }
}
