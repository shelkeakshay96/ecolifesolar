<?php

/**
 * The page whitelist.
 *
 * This is what makes the pretty-URL router safe: /about resolves only because
 * 'about' is a key here, so no path a visitor invents can reach a template.
 * Adding a page is a line here plus a .phtml -- no controller, no route entry.
 *
 * 'home' is special only in that the router maps '/' to it.
 */

declare(strict_types=1);

return [
    'home' => [
        'title'       => 'Rooftop solar in Satara, done right | Eco Life',
        'description' => 'Rooftop solar panels, solar water heaters, battery storage and '
                       . 'electrical work for homes, housing societies and businesses across Satara.',
        'template'    => 'EcoLife_Cms::page/home.phtml',
        'body_class'  => 'page-home',
    ],
    'about' => [
        'title'       => 'About Eco Life | Rooftop solar in Satara',
        'description' => 'A family-run solar installer based in Satara, Maharashtra. '
                       . 'Site-specific design, honest estimates and local support after installation.',
        'template'    => 'EcoLife_Cms::page/about.phtml',
        'body_class'  => 'page-about',
    ],
    'services' => [
        'title'       => 'What we install | Eco Life',
        'description' => 'Rooftop solar panels, solar water heaters, battery energy storage '
                       . 'and general electrical work across Satara district.',
        'template'    => 'EcoLife_Cms::page/services.phtml',
        'body_class'  => 'page-services',
    ],
    'contact' => [
        'title'       => 'Request a free site visit | Eco Life',
        'description' => 'Tell us about your roof and your electricity bill, and we will '
                       . 'arrange a free, no-pressure site visit in Satara.',
        'template'    => 'EcoLife_Cms::page/contact.phtml',
        'body_class'  => 'page-contact',
    ],
];
