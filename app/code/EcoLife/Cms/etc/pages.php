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
        'title'       => 'About Eco Life | Renewable energy in Satara',
        'description' => 'A renewable energy business based in Satara, Maharashtra, installing '
                       . 'rooftop solar since 2002. Site-specific design, honest estimates and '
                       . 'local support after installation.',
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

    /*
     * Service areas. Four entries, one template: Cms\Controller\Page\View passes
     * the identifier through to the block, and Cms\Block\Page::getServiceArea()
     * looks the town up from it. That is why these need no controller, no
     * router and no new module.
     *
     * The slug is "solar-in-<town>" rather than a bare town name because these
     * exist for someone typing "solar in karad" into a search box, and because
     * a bare /karad would sit in the same namespace as /about and /contact.
     */
    'solar-in-satara' => [
        'title'       => 'Rooftop solar in Satara city | Eco Life',
        'description' => 'Rooftop solar panels, water heaters and battery storage for homes, '
                       . 'housing societies and businesses in Satara city. Free site visit, '
                       . 'PM Surya Ghar subsidy paperwork handled.',
        'template'    => 'EcoLife_Cms::page/service-area.phtml',
        'body_class'  => 'page-area',
    ],
    'solar-in-karad' => [
        'title'       => 'Rooftop solar in Karad | Eco Life',
        'description' => 'Solar installation in Karad and the surrounding villages, by a '
                       . 'Satara-based family business. Free survey and a written estimate.',
        'template'    => 'EcoLife_Cms::page/service-area.phtml',
        'body_class'  => 'page-area',
    ],
    'solar-in-wai' => [
        'title'       => 'Rooftop solar in Wai | Eco Life',
        'description' => 'Rooftop solar for homes and businesses in Wai, installed and '
                       . 'supported from Satara. Free site visit, no obligation.',
        'template'    => 'EcoLife_Cms::page/service-area.phtml',
        'body_class'  => 'page-area',
    ],
    'solar-in-phaltan' => [
        'title'       => 'Rooftop solar in Phaltan | Eco Life',
        'description' => 'Solar panels, solar pumps and water heaters in Phaltan and the '
                       . 'surrounding taluka. Local installation and local support.',
        'template'    => 'EcoLife_Cms::page/service-area.phtml',
        'body_class'  => 'page-area',
    ],
];
