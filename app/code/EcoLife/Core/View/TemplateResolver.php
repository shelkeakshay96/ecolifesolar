<?php

declare(strict_types=1);

namespace EcoLife\Core\View;

use EcoLife\Core\App\Area;
use EcoLife\Core\Module\ModuleList;
use InvalidArgumentException;

/**
 * Turns "EcoLife_Cms::page/contact.phtml" into an absolute file path.
 *
 * Resolution order, current area first then base:
 *
 *   app/code/EcoLife/Cms/view/{area}/templates/page/contact.phtml
 *   app/code/EcoLife/Cms/view/base/templates/page/contact.phtml
 *
 * This two-entry list is the entire area mechanism for templates. It is not a
 * theme fallback chain and must not grow into one -- no app/design, no theme
 * inheritance, no registered fallback order.
 */
final class TemplateResolver
{
    /** @var array<string, string> identifier => absolute path */
    private array $cache = [];

    public function __construct(private readonly Area $area)
    {
    }

    public function resolve(string $identifier): string
    {
        if (isset($this->cache[$identifier])) {
            return $this->cache[$identifier];
        }

        if (!str_contains($identifier, '::')) {
            throw new InvalidArgumentException(
                "Template '{$identifier}' must be given as Module_Name::path/to/file.phtml"
            );
        }

        [$module, $relative] = explode('::', $identifier, 2);

        $relative = ltrim($relative, '/');
        if (str_contains($relative, '..')) {
            throw new InvalidArgumentException("Template path '{$relative}' may not traverse directories");
        }

        $base = ModuleList::path($module);
        if ($base === null) {
            throw new InvalidArgumentException(
                "Template '{$identifier}' refers to module {$module}, which is not enabled"
            );
        }

        foreach ([$this->area->getCode(), Area::BASE] as $area) {
            $candidate = "{$base}/view/{$area}/templates/{$relative}";
            if (is_file($candidate)) {
                return $this->cache[$identifier] = $candidate;
            }
        }

        throw new InvalidArgumentException(
            "Template '{$identifier}' not found in area '{$this->area->getCode()}' or base"
        );
    }

    public function exists(string $identifier): bool
    {
        try {
            $this->resolve($identifier);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
