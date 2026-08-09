<?php

declare(strict_types=1);

namespace EcoLife\Core\View\Element;

/**
 * A block with no logic of its own -- a template plus whatever data was handed
 * to it.
 *
 * Used for the header, footer and other chrome, where the template needs a
 * $block to call escapeHtml() and getSetting() on but there is nothing worth
 * writing a class for. The moment one of those needs real logic, it gets its
 * own subclass instead of a growing pile of data keys.
 */
final class Template extends AbstractBlock
{
}
