<?php

declare(strict_types=1);

namespace EcoLife\Core\Controller;

use EcoLife\Core\App\Response;

/**
 * What a controller returns. Controllers never echo and never call header() --
 * they describe a result, and the result writes itself into the Response.
 *
 * Implementations: Result\Page, Result\Json, Result\Redirect, Result\Raw.
 */
interface ResultInterface
{
    public function renderResult(Response $response): void;
}
