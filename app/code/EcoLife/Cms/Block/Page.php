<?php

declare(strict_types=1);

namespace EcoLife\Cms\Block;

use EcoLife\Core\View\Element\AbstractBlock;

/**
 * View model for the CMS page templates.
 *
 * The service and process lists live here rather than in markup so that the
 * home page and the services page cannot drift apart -- they render the same
 * arrays.
 */
final class Page extends AbstractBlock
{
    public function getIdentifier(): string
    {
        return (string) $this->getData('identifier', 'home');
    }

    /**
     * The four things the business installs. Carried over from the original
     * static site, where they were four hand-written cards.
     *
     * @return list<array{title: string, summary: string, points: list<string>, icon: string}>
     */
    public function getServices(): array
    {
        return [
            [
                'title'   => 'Rooftop Solar Panels',
                'summary' => 'Grid-connected rooftop systems sized to your actual electricity bill, '
                           . 'for homes, housing societies and commercial roofs.',
                'points'  => [
                    'On-grid systems with net metering',
                    'Sized from your last twelve months of bills',
                    'RCC, metal sheet and tiled roof mounting',
                    'PM Surya Ghar subsidy paperwork assistance',
                ],
                'icon'    => 'panel',
            ],
            [
                'title'   => 'Solar Water Heaters',
                'summary' => 'Evacuated tube and flat plate systems that take the water heating '
                           . 'load off your electricity bill entirely.',
                'points'  => [
                    '100 to 500 litre capacities',
                    'Suits homes and small hotels',
                    'Low maintenance, long service life',
                ],
                'icon'    => 'heater',
            ],
            [
                'title'   => 'Battery Energy Storage',
                'summary' => 'Store what your panels generate and keep the essentials running '
                           . 'through a power cut.',
                'points'  => [
                    'Hybrid inverter systems',
                    'Backup for lights, fans and pumps',
                    'Sized around your critical load',
                ],
                'icon'    => 'battery',
            ],
            [
                'title'   => 'Electrical Work',
                'summary' => 'The wiring, earthing and panel work that has to be right before '
                           . 'any of the above is safe to connect.',
                'points'  => [
                    'Distribution boards and earthing',
                    'Wiring upgrades and safety checks',
                    'Repairs and maintenance',
                ],
                'icon'    => 'bolt',
            ],
        ];
    }

    /**
     * The four-step process, carried over verbatim from the original site.
     *
     * @return list<array{step: string, title: string, text: string}>
     */
    public function getProcess(): array
    {
        return [
            ['step' => '01', 'title' => 'Free Site Visit',
             'text' => 'We come to you, look at the roof, check the shading through the day and '
                     . 'read your last electricity bill. No charge, no obligation.'],
            ['step' => '02', 'title' => 'Custom Design',
             'text' => 'A system sized for your consumption and your roof, with a written estimate '
                     . 'of what it costs and what it saves.'],
            ['step' => '03', 'title' => 'Installation',
             'text' => 'Mounting, panels, inverter, wiring and the paperwork for net metering, '
                     . 'handled by our own team.'],
            ['step' => '04', 'title' => 'Savings Begin',
             'text' => 'Your meter starts running backwards during the day, and we stay reachable '
                     . 'for service afterwards.'],
        ];
    }

    /**
     * The trust strip. Feature-based rather than numeric, as on the original
     * site -- the README noted that real statistics should replace this if the
     * family has any, and they have not been supplied yet.
     *
     * @return list<array{title: string, text: string}>
     */
    public function getDifferentiators(): array
    {
        return [
            ['title' => 'Site-specific design',
             'text'  => 'No catalogue packages. The system is sized from your bill and your roof.'],
            ['title' => 'Free, no-pressure survey',
             'text'  => 'We will tell you if solar is a poor fit for your roof. That happens.'],
            ['title' => 'Homes and societies',
             'text'  => 'From a single 3 kW rooftop to a housing society common-area supply.'],
            ['title' => 'Local install and support',
             'text'  => 'Based in Satara. When something needs attention, we are half an hour away.'],
        ];
    }

    public function getWhatsappLink(): string
    {
        $number = preg_replace('/\D+/', '', $this->getSetting('general/contact/whatsapp'));
        return $number === '' ? '' : 'https://wa.me/' . $number;
    }
}
