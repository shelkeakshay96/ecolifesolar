<?php

declare(strict_types=1);

namespace EcoLife\Core\App;

use EcoLife\Core\App\Router\RouterInterface;
use EcoLife\Core\Controller\AbstractAction;
use EcoLife\Core\Controller\AuthEnforcedInterface;
use EcoLife\Core\Exception\NotFoundException;
use EcoLife\Core\Module\ModuleList;
use LogicException;

/**
 * Walks the routers in order and returns the first action one of them produces.
 *
 * Routers are declared per module in etc/routers.php, so Core never has to name
 * Cms\App\Router\Page. Ordering is by getSortOrder(), lowest first: the Cms
 * pretty-URL router (10) gets a look at /about before the standard router (100)
 * tries to read it as a front name.
 */
final class FrontController
{
    /** @var list<RouterInterface>|null */
    private static ?array $routers = null;

    public function match(Context $context): AbstractAction
    {
        foreach (self::routers() as $router) {
            $action = $router->match($context);
            if ($action === null) {
                continue;
            }

            $this->assertAdminAuthEnforced($context, $action);

            return $action;
        }

        throw new NotFoundException('No route matched ' . $context->getRequest()->getPathInfo());
    }

    /**
     * The admin-base invariant, and the second of the three layers protecting
     * the admin panel.
     *
     * An adminhtml action that does not implement AuthEnforcedInterface has not
     * inherited the login check, so it is not merely unprotected -- it is a
     * mistake. Failing loudly on the first request is the point: a silent
     * 200 on an unauthenticated admin screen could go unnoticed for months.
     */
    private function assertAdminAuthEnforced(Context $context, AbstractAction $action): void
    {
        if (!$context->getArea()->isAdmin()) {
            return;
        }

        if (!$action instanceof AuthEnforcedInterface) {
            throw new LogicException(sprintf(
                '%s is an adminhtml controller but does not implement %s. '
                . 'Extend EcoLife\\Backend\\App\\Action\\AbstractAction instead of the Core one.',
                $action::class,
                AuthEnforcedInterface::class
            ));
        }
    }

    /** @return list<RouterInterface> */
    public static function routers(): array
    {
        if (self::$routers !== null) {
            return self::$routers;
        }

        $routers = [];

        foreach (ModuleList::configFiles('routers.php') as $file) {
            foreach ((array) require $file as $class) {
                if (!is_string($class) || !class_exists($class)) {
                    continue;
                }
                $router = new $class();
                if ($router instanceof RouterInterface) {
                    $routers[$class] = $router;
                }
            }
        }

        $routers = array_values($routers);
        usort($routers, static fn(RouterInterface $a, RouterInterface $b) => $a->getSortOrder() <=> $b->getSortOrder());

        return self::$routers = $routers;
    }

    public static function reset(): void
    {
        self::$routers = null;
    }
}
