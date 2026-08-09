<?php

declare(strict_types=1);

namespace EcoLife\Lead\Block;

use EcoLife\Core\View\Element\AbstractBlock;
use EcoLife\Lead\Model\Lead;
use EcoLife\Lead\Model\SpamGuard;

/**
 * View model for the enquiry form. Rendered into the home and contact pages by
 * an explicit renderChild() call, which is how cross-module composition works
 * here -- no layout XML, no configured references.
 */
final class Form extends AbstractBlock
{
    public function getPostUrl(): string
    {
        return $this->getFrontendUrl('lead/form/post');
    }

    /** Where on the site this form sits, stored for attribution. */
    public function getSource(): string
    {
        return (string) $this->getData('source', 'home');
    }

    public function getRenderedAt(): string
    {
        return (new SpamGuard($this->context))->issueTimestamp();
    }

    public function getHoneypotField(): string
    {
        return SpamGuard::HONEYPOT_FIELD;
    }

    public function getTimestampField(): string
    {
        return SpamGuard::TIMESTAMP_FIELD;
    }

    /** @return array<string, string> */
    public function getTypes(): array
    {
        return [
            'residential' => 'My home',
            'society'     => 'Housing society',
            'commercial'  => 'Business premises',
        ];
    }

    /** @return array<string, string> */
    public function getBillRanges(): array
    {
        return Lead::BILL_RANGES;
    }

    /** @return array<string, string> */
    public function getRoofTypes(): array
    {
        return Lead::ROOF_TYPES;
    }

    /** @return array<string, string> */
    public function getSocietyDesignations(): array
    {
        return Lead::SOCIETY_DESIGNATIONS;
    }

    /** @return array<string, string> */
    public function getAgmStatuses(): array
    {
        return Lead::AGM_STATUSES;
    }

    /** UTM values carried through from the landing URL, if any. */
    public function getUtm(string $key): string
    {
        return (string) $this->getRequest()->getQuery('utm_' . $key, '');
    }
}
