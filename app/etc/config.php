<?php

/**
 * Module on/off flags. Committed.
 *
 * ModuleList::load() globs every registration.php, then filters by this list:
 * a module absent from here, or set to 0, is not loaded at all. Its routes,
 * controllers and migrations all disappear together.
 *
 * All eight Phase 1 modules are declared now so the intended set is on record.
 *
 * One caveat this list does not make obvious: setting a module to 0 does not
 * make a section of another module's page quietly vanish. TemplateResolver is
 * enablement-aware and throws for a template belonging to a disabled module,
 * and renderChild() resolves the template before it renders anything -- so
 * disabling EcoLife_Lead or EcoLife_Testimonial takes the home page down with
 * it rather than dropping their sections. Turning one off means editing the
 * templates that compose it too.
 */

declare(strict_types=1);

return [
    'modules' => [
        'EcoLife_Core'        => 1,
        'EcoLife_Theme'       => 1,
        'EcoLife_Backend'     => 1,
        'EcoLife_Mail'        => 1,
        'EcoLife_Cms'         => 1,
        'EcoLife_Lead'        => 1,
        'EcoLife_Calculator'  => 1,
        'EcoLife_Gallery'     => 1,
        'EcoLife_Testimonial' => 1,
        'EcoLife_Seo'         => 1,
    ],
];
