<?php

/**
 * Savings calculator rules.
 *
 * Ported from js/main.js:205-222 of the original static site, where they were
 * client-side. Moving them here means the numbers can be corrected without a
 * developer touching JavaScript, and the assumptions are not readable by
 * anyone who opens devtools.
 *
 * OPEN ITEM, flagged in the design document: the ₹55,000/kW figure and the
 * subsidy tiers are carried over from the original site and nobody has
 * confirmed they are current. Both need checking with Piyush before launch.
 */

declare(strict_types=1);

return [
    'roof_factor' => [
        'rcc'         => 1.00,
        'metal_sheet' => 0.92,
        'tiled'       => 0.85,
        'other'       => 0.90,
    ],

    'offset' => [
        'panels'       => 0.85,
        'water_heater' => 0.25,
    ],

    // Rupees per kW of installed capacity, before subsidy.
    'cost_per_kw'         => 55000,
    'water_heater_cost'   => 22000,

    'sizing' => [
        'kw_per_1000_rupees' => 0.9,
        'water_heater_factor' => 0.4,
        'min_kw'              => 1,
        'max_kw'              => 100,
    ],

    'limits' => [
        'min_bill' => 300,
        'max_bill' => 500000,
    ],

    'disclaimer' => 'These are illustrative estimates based on typical Maharashtra '
                  . 'conditions. Your actual generation and savings depend on shading, '
                  . 'roof orientation and your tariff, and can only be confirmed after '
                  . 'a site visit.',
];
