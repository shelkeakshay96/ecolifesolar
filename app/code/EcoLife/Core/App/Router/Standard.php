<?php

declare(strict_types=1);

namespace EcoLife\Core\App\Router;

use EcoLife\Core\App\Context;
use EcoLife\Core\Controller\AbstractAction;
use EcoLife\Core\Module\ModuleList;

/**
 * The conventional router: /{frontName}/{controller}/{action}/{key}/{value}/...
 *
 * Controller and action both default to "index", so /calculator is
 * Calculator\Controller\Index\Index.
 *
 * Front names come from each module's etc/{area}/routes.xml, which is why the
 * same module can expose "lead" on the frontend and "leads" in the admin. The
 * plural/singular split there is not cosmetic: /admin/lead/view/id/5 cannot
 * work under route/controller/action, because it parses as frontName "lead",
 * controller "view", action "id".
 */
final class Standard implements RouterInterface
{
    private const SEGMENT = '/^[a-z0-9][a-z0-9_-]*$/';

    /** @var array<string, array<string, string>> area => frontName => module */
    private static array $routes = [];

    public function getSortOrder(): int
    {
        return 100;
    }

    public function match(Context $context): ?AbstractAction
    {
        $request  = $context->getRequest();
        $area     = $context->getArea();
        $segments = $request->getSegments();

        if ($segments === []) {
            return null;
        }

        // One rule blocks traversal, encoded separators and case tricks for
        // every segment, before any of them is used to build a class name.
        foreach ($segments as $segment) {
            if (!preg_match(self::SEGMENT, $segment)) {
                return null;
            }
        }

        $frontName  = $segments[0];
        $controller = $segments[1] ?? 'index';
        $action     = $segments[2] ?? 'index';

        $module = self::routes($area->getCode())[$frontName] ?? null;
        if ($module === null) {
            return null;
        }

        $class = sprintf(
            '%s\\Controller\\%s%s\\%s',
            str_replace('_', '\\', $module),
            $area->isAdmin() ? 'Adminhtml\\' : '',
            self::camel($controller),
            self::camel($action)
        );

        if (!class_exists($class) || !is_subclass_of($class, AbstractAction::class)) {
            return null;
        }

        $request->setParams(self::pathParams(array_slice($segments, 3)));

        return new $class($context);
    }

    /**
     * Trailing /key/value/key/value pairs. A trailing key with no value is
     * dropped rather than defaulted, so /leads/lead/view/id yields no id and
     * the controller's own validation reports a missing parameter.
     *
     * @param list<string> $segments
     * @return array<string, string>
     */
    private static function pathParams(array $segments): array
    {
        $params = [];
        for ($i = 0, $n = count($segments); $i + 1 < $n; $i += 2) {
            $params[$segments[$i]] = $segments[$i + 1];
        }
        return $params;
    }

    private static function camel(string $segment): string
    {
        return str_replace(['-', '_'], '', ucwords($segment, '-_'));
    }

    /**
     * frontName => module, merged from every enabled module's routes.xml for
     * the given area. Later modules cannot steal an existing front name --
     * first declaration wins, in module load order.
     *
     * @return array<string, string>
     */
    public static function routes(string $area): array
    {
        if (isset(self::$routes[$area])) {
            return self::$routes[$area];
        }

        $routes = [];

        foreach (ModuleList::configFiles($area . '/routes.xml') as $file) {
            $previous = libxml_use_internal_errors(true);
            $xml      = simplexml_load_file($file);
            libxml_use_internal_errors($previous);

            if ($xml === false) {
                continue;
            }

            foreach ($xml->route ?? [] as $route) {
                $frontName = (string) $route['frontName'];
                $module    = (string) ($route->module['name'] ?? '');

                if ($frontName === '' || $module === '' || isset($routes[$frontName])) {
                    continue;
                }
                $routes[$frontName] = $module;
            }
        }

        return self::$routes[$area] = $routes;
    }

    public static function reset(): void
    {
        self::$routes = [];
    }
}
