<?php

/**
 * The page whitelist.
 *
 * This is what makes the pretty-URL router safe: /about resolves only because
 * 'about' is a key here, so no path a visitor invents can reach a template.
 * Adding a page is a line here plus a .phtml -- no controller, no route entry.
 *
 * 'home' is special only in that the router maps '/' to it.
 *
 * The titles and descriptions here are not decoration. They are the two lines a
 * person reads in a results page before deciding whether to click, and the
 * title is a ranking input in its own right. Two rules they follow:
 *
 *   The distinguishing words come first. Titles are cut off around sixty
 *   characters, and it is always the tail that goes, so a title that opens with
 *   the brand spends its visible half saying what every other page also says.
 *
 *   No two pages target the same phrase. Two pages competing for one query do
 *   not double the chances of ranking; they split the signals that decide it
 *   and both place lower than one page would have. That is why the home page
 *   owns "solar in satara" and /solar-in-satara is aimed a level narrower, at
 *   installation within the city.
 *
 * EcoLife_Seo reads this same array to build /sitemap.xml, so a page cannot be
 * listed without existing or exist without being listed.
 */

declare(strict_types=1);

return [
    'home' => [
        'title'       => 'Solar in Satara - Rooftop Panels & Heaters | Eco Life Solar',
        'description' => 'Eco Life Solar installs rooftop solar panels and solar water heaters '
                       . 'across Satara district. Free site visit, honest estimate, local team '
                       . 'since 2002.',
        'template'    => 'EcoLife_Cms::page/home.phtml',
        'body_class'  => 'page-home',
    ],
    'about' => [
        // "Eco Life Group" is in this title because it is one of the names
        // people search for, and this is the page that explains that Eco Life
        // Group and Eco Life Green Infra LLP are the same family business.
        'title'       => 'About Eco Life Group | Solar Company in Satara',
        'description' => 'Eco Life Group has worked in Satara since 2002. Founded by Udyasingh '
                       . 'Pawar, with Sahil Pawar and Piyush Pawar running solar installation '
                       . 'across the district.',
        'template'    => 'EcoLife_Cms::page/about.phtml',
        'body_class'  => 'page-about',
    ],
    'services' => [
        'title'       => 'Solar Panels, Heaters & Pumps in Satara | Eco Life',
        'description' => 'Rooftop solar panels, water heaters, solar pumps, street lights and '
                       . 'electrical work across Satara district. Survey, subsidy paperwork and '
                       . 'install by our team.',
        'template'    => 'EcoLife_Cms::page/services.phtml',
        'body_class'  => 'page-services',
    ],
    'faq' => [
        // Aimed at the question half of the query space -- "solar panel price
        // in satara", "pm surya ghar subsidy", "how much roof space for 3kw".
        // Those are separate searches from "solar in Satara", which is why this
        // gets its own page rather than being folded into one that already
        // targets something else.
        'title'       => 'Solar Panel Price & Subsidy in Satara | FAQ',
        'description' => 'What rooftop solar costs in Satara, the PM Surya Ghar subsidy, roof '
                       . 'space needed, MSEDCL net metering and maintenance - answered plainly.',
        'template'    => 'EcoLife_Cms::page/faq.phtml',
        'body_class'  => 'page-faq',
    ],
    'contact' => [
        'title'       => 'Contact Eco Life Solar, Satara | Free Site Visit',
        'description' => 'Tell us about your roof and your electricity bill and we will arrange '
                       . 'a free, no-pressure site visit anywhere in Satara district. Phone, '
                       . 'WhatsApp or the form.',
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
        // Deliberately narrower than the home page: "installation" and "city"
        // rather than the bare "solar in Satara" the home page is aimed at.
        'title'       => 'Solar Panel Installation in Satara City | Eco Life',
        'description' => 'Rooftop solar installation across Satara city - Shahupuri, Zunjar '
                       . 'Colony, Kesarkar Peth and beyond. Our office and team are here, so a '
                       . 'site visit takes days.',
        'template'    => 'EcoLife_Cms::page/service-area.phtml',
        'body_class'  => 'page-area',
    ],
    'solar-in-karad' => [
        'title'       => 'Solar in Karad | Rooftop Solar Panels | Eco Life',
        'description' => 'Rooftop solar panel installation in Karad and the surrounding villages, '
                       . 'by a Satara family business working since 2002. Free survey and a '
                       . 'written estimate.',
        'template'    => 'EcoLife_Cms::page/service-area.phtml',
        'body_class'  => 'page-area',
    ],
    'solar-in-wai' => [
        'title'       => 'Solar in Wai | Rooftop Panels & Water Heaters',
        'description' => 'Rooftop solar panels and solar water heaters for homes and businesses '
                       . 'in Wai, installed and supported from Satara. Free site visit and a '
                       . 'shading survey.',
        'template'    => 'EcoLife_Cms::page/service-area.phtml',
        'body_class'  => 'page-area',
    ],
    'solar-in-phaltan' => [
        'title'       => 'Solar in Phaltan | Panels, Pumps & Street Lights',
        'description' => 'Solar panels, solar pumps, water heaters and street lighting across '
                       . 'Phaltan taluka, for farms, homes and small businesses. Local install, '
                       . 'local support.',
        'template'    => 'EcoLife_Cms::page/service-area.phtml',
        'body_class'  => 'page-area',
    ],
];
