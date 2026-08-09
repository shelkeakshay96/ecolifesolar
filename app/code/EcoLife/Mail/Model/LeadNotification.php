<?php

declare(strict_types=1);

namespace EcoLife\Mail\Model;

use EcoLife\Core\App\Context;
use EcoLife\Core\App\Logger;
use EcoLife\Core\Model\Settings;
use EcoLife\Core\View\Element\Template;

/**
 * "A new enquiry has arrived" to whoever is on the notification list.
 *
 * Recipients and the on/off switch are core_config rows, so the family can add
 * Sahil's address or turn notifications off during a holiday without anyone
 * touching a file.
 *
 * Reply-To is set to the customer, so hitting reply in a phone mail client goes
 * straight to them.
 */
final class LeadNotification
{
    public function __construct(private readonly Context $context)
    {
    }

    public function send(object $lead): bool
    {
        if (Settings::get('lead/notification/enabled', '1') !== '1') {
            Logger::info('Lead notifications disabled in settings', ['lead_id' => $lead->getId()]);
            return false;
        }

        $recipients = $this->recipients();

        if ($recipients === []) {
            Logger::warning('No lead notification recipients configured', ['lead_id' => $lead->getId()]);
            return false;
        }

        $block = new Template($this->context, ['lead' => $lead]);
        $body  = $block->setTemplate('EcoLife_Mail::email/lead-notification.phtml')->toHtml();

        $subject = sprintf(
            'New %s enquiry: %s, %s',
            $lead->getLeadType(),
            $lead->getName(),
            $lead->getCity()
        );

        return (new Transport())->send(
            $recipients,
            $subject,
            $body,
            $lead->getEmail() !== '' ? $lead->getEmail() : null
        );
    }

    /** @return list<string> */
    private function recipients(): array
    {
        $configured = Settings::get('lead/notification/recipients');

        $addresses = array_filter(
            array_map('trim', preg_split('/[,;\s]+/', $configured) ?: []),
            static fn(string $address) => filter_var($address, FILTER_VALIDATE_EMAIL) !== false
        );

        return array_values(array_unique($addresses));
    }
}
