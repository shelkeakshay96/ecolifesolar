<?php

/**
 * Module on/off flags. Committed.
 *
 * ModuleList::load() globs every registration.php, then filters by this list:
 * a module absent from here, or set to 0, is not loaded at all. Its routes,
 * controllers and migrations all disappear together.
 *
 * All eight Phase 1 modules are declared now so the intended set is on record.
 * Only Core, Backend, Lead and Gallery exist on disk so far -- the rest are
 * built in later steps, and ModuleList ignores a flag with no module behind it.
 */

declare(strict_types=1);

return [
    'modules' => [
        'EcoLife_Core'       => 1,
        'EcoLife_Theme'      => 1,
        'EcoLife_Backend'    => 1,
        'EcoLife_Mail'       => 1,
        'EcoLife_Cms'        => 1,
        'EcoLife_Lead'       => 1,
        'EcoLife_Calculator' => 1,
        'EcoLife_Gallery'    => 1,
    ],
];
