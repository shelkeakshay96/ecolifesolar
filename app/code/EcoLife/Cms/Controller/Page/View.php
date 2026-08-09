<?php

declare(strict_types=1);

namespace EcoLife\Cms\Controller\Page;

use EcoLife\Cms\App\Router\Page as PageRouter;
use EcoLife\Cms\Block\Page as PageBlock;
use EcoLife\Core\Controller\AbstractAction;
use EcoLife\Core\Controller\ResultInterface;
use EcoLife\Core\Exception\NotFoundException;

/**
 * One controller for every CMS page. The identifier arrives from the router,
 * which has already checked it against the whitelist -- but this checks again,
 * because a controller that trusts its input because "the router validated it"
 * is one refactor away from not being validated at all.
 */
final class View extends AbstractAction
{
    protected function execute(): ResultInterface
    {
        $identifier = (string) $this->getRequest()->getParam('identifier', 'home');
        $page       = PageRouter::pages()[$identifier] ?? null;

        if ($page === null) {
            throw new NotFoundException("No CMS page '{$identifier}'");
        }

        // The home page has exactly one address. Without this it answers on
        // both / and /home, which is duplicate content and splits any links
        // people share.
        if ($identifier === 'home' && $this->getRequest()->getPathInfo() !== '/') {
            return $this->resultRedirect()->setUrl('/')->setHttpResponseCode(301);
        }

        return $this->resultPage()
            ->setTitle($page['title'])
            ->setMetaDescription($page['description'])
            ->setBodyClass($page['body_class'])
            ->setContent(PageBlock::class, $page['template'], ['identifier' => $identifier]);
    }
}
