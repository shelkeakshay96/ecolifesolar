<?php

declare(strict_types=1);

namespace EcoLife\Core\App;

use EcoLife\Core\Model\Config;

/**
 * Which half of the application is answering: the public site or the admin
 * panel.
 *
 * Detection happens once, before routing, by looking at the first path segment
 * and comparing it to the configurable admin front name. If it matches, the
 * segment is stripped from the path so that every router downstream sees a
 * clean route/controller/action path and needs no knowledge of areas.
 *
 * This is the hinge the whole area separation turns on: template resolution,
 * route config loading and the admin auth invariant all read getCode().
 */
final class Area
{
    public const FRONTEND  = 'frontend';
    public const ADMINHTML = 'adminhtml';
    public const BASE      = 'base';

    private function __construct(private readonly string $code)
    {
    }

    public static function detect(Request $request): self
    {
        $frontName = Config::adminFrontName();
        $segments  = $request->getSegments();

        if (($segments[0] ?? null) === $frontName) {
            array_shift($segments);
            $request->setPathInfo('/' . implode('/', $segments));
            return new self(self::ADMINHTML);
        }

        return new self(self::FRONTEND);
    }

    public static function frontend(): self
    {
        return new self(self::FRONTEND);
    }

    public static function adminhtml(): self
    {
        return new self(self::ADMINHTML);
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function isAdmin(): bool
    {
        return $this->code === self::ADMINHTML;
    }

    public function isFrontend(): bool
    {
        return $this->code === self::FRONTEND;
    }

    /** The etc/ subdirectory holding this area's route configuration. */
    public function configDirectory(): string
    {
        return $this->code;
    }
}
