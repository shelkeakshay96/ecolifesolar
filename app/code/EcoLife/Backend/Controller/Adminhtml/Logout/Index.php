<?php

declare(strict_types=1);

namespace EcoLife\Backend\Controller\Adminhtml\Logout;

use EcoLife\Backend\App\Action\AbstractAction;
use EcoLife\Core\Controller\ResultInterface;

/**
 * Sign out. Not ALLOW_GUEST: signing out when not signed in is meaningless,
 * and requiring the session means a stray link cannot be used to log someone
 * out from another site.
 */
final class Index extends AbstractAction
{
    protected function execute(): ResultInterface
    {
        $this->auth->logout();
        $this->context->getMessages()->success('Signed out.');

        return $this->resultRedirect()->setUrl($this->context->getUrl()->getUrl('auth/login'));
    }
}
