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
     * The six things the business installs.
     *
     * Solar pumps and solar street lights were missing from the site entirely
     * even though the business has been doing both for years -- and neither is
     * something the Satara competitor offers at all, which makes them the two
     * most worth naming.
     *
     * `status` is 'active' or 'soon'. Battery storage is the only 'soon' one,
     * and the badge exists so it stops sitting on the page looking exactly as
     * available as the five things we can actually install tomorrow.
     *
     * @return list<array{title: string, summary: string, points: list<string>, icon: string, status: string}>
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
                'status'  => 'active',
            ],
            [
                'title'   => 'Solar Water Heaters',
                'summary' => 'Evacuated tube and flat plate systems that take the water heating '
                           . 'load off your electricity bill entirely. This is where the business '
                           . 'started, and where the 500-odd installations behind us began.',
                'points'  => [
                    '100 to 500 litre capacities',
                    'Suits homes, hostels and small hotels',
                    'Low maintenance, long service life',
                ],
                'icon'    => 'heater',
                'status'  => 'active',
            ],
            [
                'title'   => 'Solar Pumps',
                'summary' => 'Solar-powered pumping for farms and small businesses, so irrigation '
                           . 'stops depending on when the supply happens to be on.',
                'points'  => [
                    'Surface and submersible pumps',
                    'Sized to your borewell and your acreage',
                    'No diesel, no running cost',
                ],
                'icon'    => 'pump',
                'status'  => 'active',
            ],
            [
                'title'   => 'Solar Street Lights',
                'summary' => 'Standalone lighting for lanes, compounds, farms and society '
                           . 'common areas, with no cabling back to a meter.',
                'points'  => [
                    'Integrated panel, battery and LED',
                    'Dusk-to-dawn automatic operation',
                    'Suits gram panchayat and society use',
                ],
                'icon'    => 'streetlight',
                'status'  => 'active',
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
                'status'  => 'active',
            ],
            [
                'title'   => 'Battery Energy Storage',
                'summary' => 'Store what your panels generate and keep the essentials running '
                           . 'through a power cut. We are not installing these yet -- ask us and '
                           . 'we will tell you honestly where we have got to.',
                'points'  => [
                    'Hybrid inverter systems',
                    'Backup for lights, fans and pumps',
                    'Sized around your critical load',
                ],
                'icon'    => 'battery',
                'status'  => 'soon',
            ],
        ];
    }

    /**
     * What every service above includes. A strip rather than a seventh card:
     * "complete project execution" describes how the other six are delivered,
     * and giving it a card of its own would dilute the two genuinely new ones.
     *
     * @return list<string>
     */
    public function getExecutionSteps(): array
    {
        return [
            'Site survey and system design',
            'Subsidy and net-metering paperwork',
            'Supply, installation and commissioning',
            'Service and support afterwards',
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

    /**
     * The towns with their own page, keyed by the pages.php identifier.
     *
     * `town` is also the substring matched against gallery_item.location, so it
     * has to be the word the family actually types when adding a photo. Keep it
     * to the bare town name for that reason.
     *
     * @return array<string, array{town: string, headline: string, intro: string, note: string}>
     */
    public function getServiceAreas(): array
    {
        return [
            'solar-in-satara' => [
                'town'     => 'Satara',
                'headline' => 'Rooftop solar in Satara city',
                'intro'    => 'This is where we are. Our office, our team and most of our '
                            . 'installations are in Satara city, which means a site visit is '
                            . 'usually a matter of days and a service call is a short drive '
                            . 'rather than a scheduled trip.',
                'note'     => 'RCC, tiled and metal-sheet roofs across Zunjar Colony, Shahupuri, '
                            . 'Kesarkar Peth, Karanje Peth and the rest of the city.',
            ],
            'solar-in-karad' => [
                'town'     => 'Karad',
                'headline' => 'Rooftop solar in Karad',
                'intro'    => 'Karad is about an hour from our office, and we work there '
                            . 'regularly. Homes, housing societies and commercial roofs, with '
                            . 'the same free survey and the same written estimate before '
                            . 'anything is ordered.',
                'note'     => 'If you are in a village around Karad rather than in the town '
                            . 'itself, ask anyway. We usually can.',
            ],
            'solar-in-wai' => [
                'town'     => 'Wai',
                'headline' => 'Rooftop solar in Wai',
                'intro'    => 'Wai sits close enough to Satara that we treat it as home ground. '
                            . 'Rooftop panels, solar water heaters and the electrical work that '
                            . 'has to be right before either is safe to connect.',
                'note'     => 'Shading from surrounding hills matters more here than in the '
                            . 'city, which is exactly why we survey before we quote.',
            ],
            'solar-in-phaltan' => [
                'town'     => 'Phaltan',
                'headline' => 'Rooftop solar in Phaltan',
                'intro'    => 'Phaltan taluka is agricultural as much as residential, so this '
                            . 'is where solar pumps come up as often as rooftop panels. We do '
                            . 'both, and we will tell you which one actually saves you more.',
                'note'     => 'Solar pumps, rooftop systems and street lighting for farms, '
                            . 'homes and small businesses.',
            ],
        ];
    }

    /**
     * The town this page is about, or null if the identifier is not an area
     * page. The template treats null as "nothing to render" rather than
     * throwing, so a mistyped pages.php entry degrades to a thin page instead
     * of a 500.
     *
     * @return array{town: string, headline: string, intro: string, note: string}|null
     */
    public function getServiceArea(): ?array
    {
        return $this->getServiceAreas()[$this->getIdentifier()] ?? null;
    }

    /**
     * The three founders, for the About page.
     *
     * Hardcoded here rather than given a table because there are three of them
     * and they change roughly once a decade -- a CRUD screen the family would
     * open twice would cost more to maintain than it saves.
     *
     * `photo` is a path under pub/images/. The template checks whether the file
     * is actually there and falls back to a monogram, so this array is correct
     * whether or not the photographs have been supplied yet.
     *
     * Three of the keys here exist for image search rather than for the page,
     * and they are worth explaining because they look redundant next to `name`:
     *
     *   The FILENAME is a ranking signal in its own right. These were
     *   sahil.jpg and piyush.jpg, which tell a crawler nothing -- the file is
     *   now named the way somebody would search for the person in it. Renaming
     *   an indexed image costs its accumulated history, so this is a thing to
     *   get right once rather than iterate on.
     *
     *   `photo_alt` is the strongest signal Google has for what an image
     *   depicts. It names the role and the town, tying the face to the same
     *   entity the JSON-LD describes -- and it carries BOTH forms of the name.
     *
     *   `short_name` is why. These men are searched for as "Sahil Pawar" far
     *   more often than as "Sahil Udyasingh Pawar", but the formal name is what
     *   the page, the sitemap title and the schema all use, and "Sahil Pawar"
     *   is not a contiguous substring of it. Carrying both means neither query
     *   depends on a search engine inferring that the middle name is optional.
     *   It also feeds schema.org alternateName, which is the supported way of
     *   saying two strings are the same person.
     *
     *   `photo_caption` goes into the image sitemap, not the page. It is
     *   separate from photo_alt because alt text is read aloud by screen
     *   readers and should stay short, while a caption has room to be
     *   specific.
     *
     * @return list<array{name: string, short_name: string, role: string, credential: string,
     *                    photo: string, photo_alt: string, photo_caption: string}>
     */
    public function getTeam(): array
    {
        return [
            ['name'       => 'Udyasingh Naryanrao Pawar',
             'short_name' => 'Udyasingh Pawar',
             'role'       => 'Founder, Eco Life Group',
             'credential' => 'B.E. Chemical Engineering. Twenty years in business, and the person '
                           . 'who laid the foundation the rest of this is built on.',
             'photo'      => 'images/team/udyasingh-pawar-solar-satara.jpg',
             'photo_alt'  => 'Udyasingh Pawar (Udyasingh Naryanrao Pawar), founder of Eco Life '
                           . 'Group, solar company in Satara',
             'photo_caption' => 'Udyasingh Naryanrao Pawar, founder of Eco Life Group, the '
                              . 'Satara renewable energy business he started in 2002.'],
            ['name'       => 'Sahil Udyasingh Pawar',
             'short_name' => 'Sahil Pawar',
             'role'       => 'Founder, Eco Life Green Infra LLP',
             'credential' => 'M.A. Psychology. Six years in solar. Looks after customer '
                           . 'relationships and project execution, so he is usually the one on '
                           . 'your roof.',
             'photo'      => 'images/team/sahil-pawar-solar-satara.jpg',
             'photo_alt'  => 'Sahil Pawar (Sahil Udyasingh Pawar), founder of Eco Life Green '
                           . 'Infra LLP, solar installer in Satara',
             'photo_caption' => 'Sahil Pawar, founder of Eco Life Green Infra LLP, who runs '
                              . 'rooftop solar project execution across Satara district.'],
            ['name'       => 'Piyush Udyasingh Pawar',
             'short_name' => 'Piyush Pawar',
             'role'       => 'Co-Founder, Eco Life Green Infra LLP',
             'credential' => 'B.Com, Business Administration and Finance. Eight years across '
                           . 'solar and other sectors. Handles business management and financial '
                           . 'planning.',
             'photo'      => 'images/team/piyush-pawar-solar-satara.jpg',
             'photo_alt'  => 'Piyush Pawar (Piyush Udyasingh Pawar), co-founder of Eco Life Green '
                           . 'Infra LLP, solar company in Satara',
             'photo_caption' => 'Piyush Pawar, co-founder of Eco Life Green Infra LLP, who '
                              . 'handles business management for the Satara solar business.'],
        ];
    }

    /**
     * True when the photograph for a team member is actually on disk.
     *
     * getStaticUrl() deliberately returns a URL for a missing file, because a
     * 404 on a stylesheet is easier to debug than a silently omitted link. That
     * is the wrong trade for a face: a broken-image icon where a founder should
     * be is worse than no photograph at all.
     */
    public function hasStaticFile(string $file): bool
    {
        return is_file(BP . '/pub/' . ltrim($file, '/'));
    }

    /** Initials for the monogram shown until a photograph exists. */
    public function getInitials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = $parts[0] ?? '';
        $last  = count($parts) > 1 ? $parts[count($parts) - 1] : '';

        return mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
    }

    public function getWhatsappLink(): string
    {
        $number = preg_replace('/\D+/', '', $this->getSetting('general/contact/whatsapp'));
        return $number === '' ? '' : 'https://wa.me/' . $number;
    }
}
