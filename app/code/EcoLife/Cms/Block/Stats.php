<?php

declare(strict_types=1);

namespace EcoLife\Cms\Block;

use EcoLife\Core\View\Element\AbstractBlock;

/**
 * The trust band: four figures the family maintains from /admin/settings.
 *
 * The split between this class and core_config is deliberate. The *number*
 * changes -- another rooftop is another installation -- so it is a config row.
 * The *suffix* and the *label* are typography and copy, so they are here. That
 * split is also what keeps the stored value a bare integer, which is what
 * data-count-to needs and what is easiest to type into a settings field.
 *
 * This closes the TODO in Page::getDifferentiators(): the trust strip was
 * feature-based rather than numeric because the family had supplied no
 * statistics. They have now.
 */
final class Stats extends AbstractBlock
{
    /**
     * In display order.
     *
     * @var list<array{path: string, suffix: string, label: string, note: string}>
     */
    private const FIGURES = [
        ['path'  => 'general/stats/installations',    'suffix' => '+',
         'label' => 'Rooftop installations',          'note'   => 'across Satara and the district'],
        ['path'  => 'general/stats/capacity_kw',      'suffix' => ' KW+',
         'label' => 'Cumulative capacity',            'note'   => 'installed and generating'],
        ['path'  => 'general/stats/water_heaters',    'suffix' => '+',
         'label' => 'Solar water heaters',            'note'   => 'where the business started'],
        ['path'  => 'general/stats/experience_years', 'suffix' => '+',
         'label' => 'Years of group experience',      'note'   => 'Eco Life Group, since 2002'],
    ];

    /** @var list<array{value: int, display: string, suffix: string, label: string, note: string}>|null */
    private ?array $figures = null;

    /**
     * A blank or non-numeric row means the family cleared that figure, so the
     * tile is dropped rather than rendered as "0+" -- which would read as a
     * claim to have installed nothing at all. Settings::get() returns '' both
     * for a missing row and for a NULL one, so one check covers both.
     *
     * @return list<array{value: int, display: string, suffix: string, label: string, note: string}>
     */
    public function getFigures(): array
    {
        if ($this->figures !== null) {
            return $this->figures;
        }

        $figures = [];

        foreach (self::FIGURES as $figure) {
            $raw = $this->getSetting($figure['path']);

            if (!is_numeric($raw) || (int) $raw <= 0) {
                continue;
            }

            $value = (int) $raw;

            $figures[] = [
                'value'   => $value,
                // Indian digit grouping, matching toLocaleString('en-IN') in
                // app.js, so the server-rendered fallback and the last frame of
                // the count-up agree character for character. formatCurrency is
                // named for its usual caller, not for what it does here.
                'display' => $this->formatCurrency($value, false) . $figure['suffix'],
                'suffix'  => $figure['suffix'],
                'label'   => $figure['label'],
                'note'    => $figure['note'],
            ];
        }

        return $this->figures = $figures;
    }

    /**
     * Real, checkable credentials rather than a wall of borrowed brand logos.
     * Rendered under the figures on the same band.
     *
     * @return list<string>
     */
    public function getCredentials(): array
    {
        return ['MNRE registered', 'DISCOM empanelled', 'Waaree channel partner'];
    }
}
