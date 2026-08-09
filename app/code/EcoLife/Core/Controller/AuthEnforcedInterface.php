<?php

declare(strict_types=1);

namespace EcoLife\Core\Controller;

/**
 * Marker: "this action has had authentication applied to it."
 *
 * Backend\App\Action\AbstractAction implements it and is final in its
 * dispatch(), so the only way to satisfy this interface is to inherit the real
 * check. FrontController refuses to dispatch an adminhtml action that does not
 * implement it -- which turns "I forgot to extend the admin base class" from a
 * silent authentication bypass into a LogicException on the first request.
 *
 * It lives in Core, not Backend, because Core is not allowed to name a
 * non-Core class and FrontController is the thing doing the enforcing.
 */
interface AuthEnforcedInterface
{
}
