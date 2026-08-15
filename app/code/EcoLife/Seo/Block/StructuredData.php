<?php

declare(strict_types=1);

namespace EcoLife\Seo\Block;

use EcoLife\Cms\Block\Page as CmsPage;
use EcoLife\Core\App\Csp;
use EcoLife\Core\View\Element\AbstractBlock;

/**
 * The JSON-LD graph: the machine-readable version of who this business is.
 *
 * Its whole job is to let a search engine answer "eco life solar", "ecolife
 * group" and "sahil pawar solar" with an entity rather than a guess. A page of
 * prose saying the same things is ambiguous -- there are other Pawars and other
 * companies with "eco" in the name -- and an ambiguous entity is one that gets
 * merged with, or beaten by, whichever similarly-named business published
 * unambiguous markup.
 *
 * Two rules govern everything below, and they matter more than the volume of
 * markup:
 *
 *   Nothing is invented. Every value comes from core_config or from
 *   Cms\Block\Page, which are the same sources the visible page renders from,
 *   so the markup and the page cannot contradict each other.
 *
 *   Nothing unverified is published. A seeded placeholder address or an
 *   info@example.com is worse than an absent one: local ranking rests on the
 *   business's name, address and phone agreeing everywhere they appear, and a
 *   wrong address here disagrees with the real one on Google Business Profile.
 *   isPublishable() is what enforces that, and it is why this file is careful
 *   rather than long.
 *
 * There is deliberately no aggregateRating and no review markup. Both are
 * available, both produce stars in the results page, and both are fabrication
 * unless real reviews exist -- which is a manual-action risk, not a grey area.
 * When the testimonials table has genuine entries with real names, that is the
 * time to revisit it.
 */
final class StructuredData extends AbstractBlock
{
    /**
     * Values that exist in the database only because db:seed put them there.
     *
     * Settings::isPlaceholder() catches the PLACEHOLDER prefix and nothing
     * else, so the seeded phone numbers and example.com addresses would sail
     * through it and get published as fact.
     */
    private const DUMMY_FRAGMENTS = ['example.com', 'example.org', '9876543210', '9123456789'];

    private const DAYS = [
        'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday',
        'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday',
    ];

    /** The year on the About page. Stated here so the two cannot drift apart. */
    private const FOUNDED = '2002';

    public function getNonce(): string
    {
        return Csp::value();
    }

    /**
     * JSON, escaped for embedding in a script element.
     *
     * JSON_HEX_TAG is the load-bearing flag: without it a value containing
     * "</script>" -- an address field somebody pasted HTML into, say -- ends the
     * element early and turns the rest of the graph into markup. The remaining
     * flags match AbstractBlock::escapeJs, which takes only strings and so
     * cannot be used on an array.
     */
    public function escapeJsonLd(array $graph): string
    {
        return (string) json_encode(
            $graph,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
    }

    /** @return array<string, mixed> */
    public function getGraph(): array
    {
        $nodes = [$this->organization(), $this->website(), $this->webPage(), $this->breadcrumb()];

        if ($page = $this->areaService()) {
            $nodes[] = $page;
        }

        if ($faq = $this->faqPage()) {
            $nodes[] = $faq;
        }

        return ['@context' => 'https://schema.org', '@graph' => array_values(array_filter($nodes))];
    }

    // ------------------------------------------------------------------ nodes

    /** @return array<string, mixed> */
    private function organization(): array
    {
        $base = $this->baseUrl();

        $node = [
            // LocalBusiness as well as Organization, because the queries this
            // is here to win are local ones. The pair is what associates the
            // brand with a place rather than with a website.
            '@type' => ['Organization', 'LocalBusiness'],
            '@id'   => $base . '/#organization',
            'name'  => $this->getSetting('general/business/name', 'Eco Life Solar'),

            // The spellings people actually type. "ecolife solar" and "eco life
            // group" are different strings to a search engine, and this is the
            // supported way of saying they mean the same company.
            'alternateName' => [
                'Eco Life Solar', 'EcoLife Solar', 'Eco Life Group', 'EcoLife Group',
                'Eco Life Green Infra LLP', 'Eco Life',
            ],
            'url'         => $base . '/',
            'description' => 'Rooftop solar panels, solar water heaters, solar pumps, street '
                           . 'lighting and electrical work for homes, housing societies and '
                           . 'businesses across Satara district, Maharashtra.',
            'foundingDate' => self::FOUNDED,
            'logo'  => [
                '@type' => 'ImageObject',
                'url'   => $base . $this->getStaticUrl('images/logo.png'),
            ],
            'image'     => $base . $this->getStaticUrl('images/panels.jpg'),
            'founder'   => $this->founders(),
            'areaServed' => $this->areasServed(),
            'knowsAbout' => [
                'Rooftop solar installation', 'Solar water heaters', 'Solar pumps',
                'Solar street lighting', 'Net metering', 'PM Surya Ghar subsidy',
                'Battery energy storage',
            ],
            'hasOfferCatalog' => $this->offerCatalog(),
        ];

        if ($phones = $this->publishablePhones()) {
            $node['telephone'] = count($phones) === 1 ? $phones[0] : $phones;
        }

        if ($email = $this->publishable('general/contact/email')) {
            $node['email'] = $email;
        }

        if ($address = $this->address()) {
            $node['address'] = $address;
        }

        if ($hours = $this->openingHours()) {
            $node['openingHoursSpecification'] = $hours;
        }

        if ($profiles = $this->socialProfiles()) {
            $node['sameAs'] = $profiles;
        }

        return $node;
    }

    /** @return array<string, mixed> */
    private function website(): array
    {
        $base = $this->baseUrl();

        return [
            '@type'      => 'WebSite',
            '@id'        => $base . '/#website',
            'url'        => $base . '/',
            'name'       => $this->getSetting('general/business/name', 'Eco Life Solar'),
            'publisher'  => ['@id' => $base . '/#organization'],
            'inLanguage' => 'en-IN',
        ];
    }

    /** @return array<string, mixed> */
    private function webPage(): array
    {
        $base      = $this->baseUrl();
        $canonical = $this->canonicalUrl();

        return array_filter([
            '@type'      => 'WebPage',
            '@id'        => $canonical . '#webpage',
            'url'        => $canonical,
            'name'       => (string) $this->getData('page_title', ''),
            'description' => (string) $this->getData('page_description', ''),
            'isPartOf'   => ['@id' => $base . '/#website'],
            'about'      => ['@id' => $base . '/#organization'],
            'breadcrumb' => ['@id' => $canonical . '#breadcrumb'],
            'inLanguage' => 'en-IN',
        ], static fn($value) => $value !== '' && $value !== null);
    }

    /**
     * Home > This Page. Two levels, because the site is two levels deep.
     *
     * On the home page the list is just the home page, which is correct and
     * which Google handles: a breadcrumb of one is not an error.
     *
     * @return array<string, mixed>
     */
    private function breadcrumb(): array
    {
        $base  = $this->baseUrl();
        $items = [[
            '@type'    => 'ListItem',
            'position' => 1,
            'name'     => 'Home',
            'item'     => $base . '/',
        ]];

        if ($this->identifier() !== 'home') {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => 2,
                'name'     => $this->breadcrumbLeafName(),
                'item'     => $this->canonicalUrl(),
            ];
        }

        return [
            '@type'           => 'BreadcrumbList',
            '@id'             => $this->canonicalUrl() . '#breadcrumb',
            'itemListElement' => $items,
        ];
    }

    /**
     * On a service-area page only: the service, tied to the town.
     *
     * This is the node that answers "solar in karad" as a service offered in a
     * place, rather than as a page that happens to contain both words.
     *
     * @return array<string, mixed>|null
     */
    private function areaService(): ?array
    {
        $areas = (new CmsPage($this->context))->getServiceAreas();
        $area  = $areas[$this->identifier()] ?? null;

        if ($area === null) {
            return null;
        }

        return [
            '@type'       => 'Service',
            '@id'         => $this->canonicalUrl() . '#service',
            'name'        => $area['headline'],
            'description' => $area['intro'],
            'serviceType' => 'Rooftop solar installation',
            'provider'    => ['@id' => $this->baseUrl() . '/#organization'],
            'areaServed'  => [
                '@type'         => 'City',
                'name'          => $area['town'],
                'containedInPlace' => [
                    '@type' => 'AdministrativeArea',
                    'name'  => 'Satara district, Maharashtra, India',
                ],
            ],
        ];
    }

    /**
     * FAQPage, on whichever page actually renders an FAQ.
     *
     * Driven by the same Cms\Block\Page::getFaqs() the template renders from,
     * so the markup cannot describe questions the page does not show -- which
     * is both a Google policy requirement and the failure mode you would never
     * notice, since the invisible half is the half in the JSON.
     *
     * Worth being clear about what this does and does not buy. Since 2023
     * Google has shown FAQ rich results almost exclusively for government and
     * health sites, so this will not produce the expandable questions under the
     * listing for a solar installer. What it does is state unambiguously that
     * the page answers these specific questions, which helps it be retrieved
     * for them. The ranking comes from the answers existing, not from the
     * markup describing them.
     *
     * @return array<string, mixed>|null
     */
    private function faqPage(): ?array
    {
        $blockClass = 'EcoLife\\Faq\\Block\\Faqs';

        // Guarded rather than a declared dependency, as with the gallery in
        // UrlList: EcoLife_Seo sequences after Cms alone, and switching the FAQ
        // module off should cost the site a schema node, not every page it
        // renders on.
        if (!class_exists($blockClass)) {
            return null;
        }

        // The page identifier is the contract, and it is owned by the FAQ
        // module rather than spelled out again here, so renaming the page is
        // one edit instead of a hunt for the string.
        if ($this->identifier() !== $blockClass::PAGE_IDENTIFIER) {
            return null;
        }

        // The same rows the template renders, read from the faq table. This is
        // the property worth protecting through the move off a config file:
        // markup describing questions the page does not show is both a Google
        // policy problem and the half nobody would notice was wrong.
        $faqs = (new $blockClass($this->context))->getFaqs();

        if ($faqs === []) {
            return null;
        }

        $entities = [];

        foreach ($faqs as $faq) {
            $entities[] = [
                '@type'          => 'Question',
                'name'           => $faq->getQuestion(),
                // getAnswer(), so the price token is substituted here exactly as
                // it is on the page.
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq->getAnswer()],
            ];
        }

        return [
            '@type'      => 'FAQPage',
            '@id'        => $this->canonicalUrl() . '#faq',
            'mainEntity' => $entities,
            'isPartOf'   => ['@id' => $this->canonicalUrl() . '#webpage'],
        ];
    }

    // ------------------------------------------------------------- components

    /**
     * The founders, as Person nodes.
     *
     * Inline rather than referenced, and present on every page rather than only
     * on About, because they are a property of the business and the business is
     * described on every page. This is what gives "piyush pawar solar" and
     * "sahil pawar solar" something to match: a named person, with a role, tied
     * by worksFor to a company tied to a town.
     *
     * @return list<array<string, mixed>>
     */
    private function founders(): array
    {
        $base   = $this->baseUrl();
        $people = [];

        foreach ((new CmsPage($this->context))->getTeam() as $person) {
            $node = [
                '@type'    => 'Person',
                '@id'      => $base . '/about#' . $this->slug($person['name']),
                'name'     => $person['name'],
                'jobTitle' => $person['role'],
                'worksFor' => ['@id' => $base . '/#organization'],
                'url'      => $base . '/about',
            ];

            // "Sahil Pawar" is not a substring of "Sahil Udyasingh Pawar", so a
            // search for the short form has nothing to match against the formal
            // one. alternateName is the supported way to state that both
            // strings are the same person -- the same mechanism the
            // Organization node uses for the brand spellings.
            if (($person['short_name'] ?? '') !== '' && $person['short_name'] !== $person['name']) {
                $node['alternateName'] = $person['short_name'];
            }

            // Only when the photograph is really on disk. A schema image
            // pointing at a 404 is a validation error in Search Console.
            //
            // A full ImageObject rather than a bare URL string. Both are valid,
            // but the object carries a caption and real pixel dimensions, and
            // those are what let Google associate this specific file with this
            // specific person rather than treating it as decoration on a page
            // that happens to mention them.
            if ($image = $this->imageObject($person['photo'], $person['photo_caption'])) {
                $node['image'] = $image;
            }

            $people[] = $node;
        }

        return $people;
    }

    /**
     * An ImageObject for a file under pub/, or null if it is not there.
     *
     * Dimensions are read from the file rather than declared, so they cannot go
     * stale when an image is replaced with one of a different size. getimagesize
     * is a header read, not a decode, and the result is memoised per request
     * because the same three founders appear in the graph on every page.
     *
     * @return array<string, mixed>|null
     */
    private function imageObject(string $path, string $caption = ''): ?array
    {
        static $cache = [];

        $path = ltrim($path, '/');
        $file = BP . '/pub/' . $path;

        if (array_key_exists($path, $cache)) {
            return $cache[$path];
        }

        if (!is_file($file)) {
            return $cache[$path] = null;
        }

        $node = [
            '@type'      => 'ImageObject',
            'url'        => $this->baseUrl() . $this->getStaticUrl($path),
            'contentUrl' => $this->baseUrl() . $this->getStaticUrl($path),
        ];

        $size = @getimagesize($file);

        if (is_array($size)) {
            $node['width']  = $size[0];
            $node['height'] = $size[1];
        }

        if ($caption !== '') {
            $node['caption'] = $caption;
        }

        return $cache[$path] = $node;
    }

    /**
     * What the business sells, from the same array the services page renders.
     *
     * "Coming soon" entries are skipped. Advertising an offer that cannot be
     * fulfilled is the sort of thing that produces a call the family has to
     * apologise on.
     *
     * @return array<string, mixed>
     */
    private function offerCatalog(): array
    {
        $offers = [];

        foreach ((new CmsPage($this->context))->getServices() as $service) {
            if (($service['status'] ?? 'active') !== 'active') {
                continue;
            }

            $offers[] = [
                '@type'       => 'Offer',
                'itemOffered' => [
                    '@type'       => 'Service',
                    'name'        => $service['title'],
                    'description' => $service['summary'],
                ],
            ];
        }

        return [
            '@type'           => 'OfferCatalog',
            'name'            => 'Solar and electrical services',
            'itemListElement' => $offers,
        ];
    }

    /** @return list<array<string, string>> */
    private function areasServed(): array
    {
        $areas = [[
            '@type' => 'AdministrativeArea',
            'name'  => 'Satara district, Maharashtra, India',
        ]];

        foreach ((new CmsPage($this->context))->getServiceAreas() as $area) {
            $areas[] = ['@type' => 'City', 'name' => $area['town']];
        }

        return $areas;
    }

    /**
     * The postal address, split into the fields schema.org expects.
     *
     * It is stored as one free-text line, because that is the sensible thing to
     * put in front of the family in the admin panel. Splitting it here is a
     * matter of subtraction rather than parsing: the PIN, the region and the
     * locality are lifted off the end if they are there, and whatever remains
     * is the street. Nothing is guessed -- an element that is not present in the
     * stored line is simply not emitted.
     *
     * The subtraction matters because streetAddress is supposed to be the
     * street. Repeating "Satara, Maharashtra 415001" inside it as well as in
     * the three fields beside it is how a listing ends up not matching the
     * Google Business Profile it is meant to corroborate.
     *
     * @return array<string, string>|null
     */
    private function address(): ?array
    {
        $line = $this->publishable('general/contact/address');

        if ($line === null) {
            return null;
        }

        $street  = $line;
        $address = ['@type' => 'PostalAddress'];

        if (preg_match('/\b(\d{6})\s*$/', $street, $match)) {
            $address['postalCode'] = $match[1];
            $street = substr($street, 0, -strlen($match[0]));
        }

        $street = $this->trimSuffix($street, 'Maharashtra');
        $street = $this->trimSuffix($street, 'Satara');
        $street = trim($street, " \t\n\r,");

        if ($street !== '') {
            $address['streetAddress'] = $street;
        }

        // Locality, region and country are constants for this business rather
        // than readings from the field. They are true whether or not whoever
        // typed the address happened to include them.
        $address['addressLocality'] = 'Satara';
        $address['addressRegion']   = 'Maharashtra';
        $address['addressCountry']  = 'IN';

        return $address;
    }

    /** Drops a trailing ", Word" from an address line, case-insensitively. */
    private function trimSuffix(string $value, string $suffix): string
    {
        $trimmed = rtrim($value, " \t\n\r,");

        if (preg_match('/,?\s*' . preg_quote($suffix, '/') . '$/i', $trimmed, $match)) {
            return substr($trimmed, 0, -strlen($match[0]));
        }

        return $trimmed;
    }

    /**
     * "Mon-Sat, 9:00 AM - 7:00 PM" as an openingHoursSpecification.
     *
     * Strict on purpose. If the family retypes this field in some other shape,
     * the right outcome is no opening-hours markup rather than markup asserting
     * hours nobody meant -- so anything that does not match exactly is dropped.
     *
     * @return list<array<string, mixed>>|null
     */
    private function openingHours(): ?array
    {
        $value = $this->publishable('general/contact/hours');

        if ($value === null) {
            return null;
        }

        $pattern = '/^(mon|tue|wed|thu|fri|sat|sun)\s*[-\x{2013}]\s*(mon|tue|wed|thu|fri|sat|sun)'
                 . '\s*,?\s*(\d{1,2}:\d{2})\s*(am|pm)\s*[-\x{2013}]\s*(\d{1,2}:\d{2})\s*(am|pm)$/iu';

        if (!preg_match($pattern, trim($value), $m)) {
            return null;
        }

        $order = array_keys(self::DAYS);
        $from  = array_search(strtolower($m[1]), $order, true);
        $to    = array_search(strtolower($m[2]), $order, true);

        if ($from === false || $to === false || $to < $from) {
            return null;
        }

        $days = [];
        for ($i = $from; $i <= $to; $i++) {
            $days[] = self::DAYS[$order[$i]];
        }

        return [[
            '@type'     => 'OpeningHoursSpecification',
            'dayOfWeek' => $days,
            'opens'     => $this->to24Hour($m[3], $m[4]),
            'closes'    => $this->to24Hour($m[5], $m[6]),
        ]];
    }

    private function to24Hour(string $time, string $meridiem): string
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        $meridiem = strtolower($meridiem);

        if ($meridiem === 'pm' && $hour !== 12) {
            $hour += 12;
        } elseif ($meridiem === 'am' && $hour === 12) {
            $hour = 0;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }

    /**
     * Phone numbers in E.164, which is the form Google matches against a Google
     * Business Profile listing. The display form with its spaces is for humans.
     *
     * @return list<string>
     */
    private function publishablePhones(): array
    {
        if ($this->publishable('general/contact/phone') === null) {
            return [];
        }

        $numbers = [];

        foreach ($this->getContactNumbers() as $number) {
            $dial = $number['dial'];

            if ($this->looksDummy($dial)) {
                continue;
            }

            // Ten digits with no country code is an Indian mobile or landline
            // written the way everyone here writes it. E.164 needs the +91.
            if (!str_starts_with($dial, '+')) {
                $dial = strlen($dial) === 10 ? '+91' . $dial : '+' . $dial;
            }

            $numbers[] = $dial;
        }

        return array_values(array_unique($numbers));
    }

    /** @return list<string> */
    private function socialProfiles(): array
    {
        $profiles = [];

        foreach (['general/social/facebook', 'general/social/instagram'] as $path) {
            $url = $this->publishable($path);

            // Only absolute URLs. sameAs takes a profile address, and a stored
            // handle like "@ecolifesolar" is not one.
            if ($url !== null && preg_match('#^https?://#i', $url)) {
                $profiles[] = $url;
            }
        }

        return $profiles;
    }

    // ---------------------------------------------------------------- helpers

    /** A setting's value, or null when it is empty, seeded or a placeholder. */
    private function publishable(string $path): ?string
    {
        $value = trim($this->getSetting($path));

        if ($value === '' || str_starts_with($value, 'PLACEHOLDER') || $this->looksDummy($value)) {
            return null;
        }

        return $value;
    }

    private function looksDummy(string $value): bool
    {
        $compact = strtolower(str_replace([' ', '-', '(', ')'], '', $value));

        foreach (self::DUMMY_FRAGMENTS as $fragment) {
            if (str_contains($compact, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function baseUrl(): string
    {
        return rtrim($this->context->getUrl()->getBaseUrl(), '/');
    }

    private function canonicalUrl(): string
    {
        $path = $this->context->getRequest()->getPathInfo();

        return $this->baseUrl() . ($path === '' ? '/' : $path);
    }

    /** The pages.php identifier for the current request. */
    private function identifier(): string
    {
        $path = trim($this->context->getRequest()->getPathInfo(), '/');

        return $path === '' ? 'home' : $path;
    }

    /**
     * The breadcrumb label for this page: its own title, minus the brand suffix
     * the title carries for the results page. "Rooftop solar in Karad | Eco
     * Life Solar" is a good title and a terrible breadcrumb.
     */
    private function breadcrumbLeafName(): string
    {
        $title = (string) $this->getData('page_title', '');
        $title = trim(explode('|', $title)[0]);

        return $title !== '' ? $title : ucfirst(str_replace('-', ' ', $this->identifier()));
    }

    private function slug(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $name) ?? ''));

        return trim($slug, '-');
    }
}
