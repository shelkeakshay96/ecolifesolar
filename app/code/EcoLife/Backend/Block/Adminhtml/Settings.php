<?php

declare(strict_types=1);

namespace EcoLife\Backend\Block\Adminhtml;

use EcoLife\Core\Model\Settings as SettingsModel;
use EcoLife\Core\View\Element\AbstractBlock;

final class Settings extends AbstractBlock
{
    /**
     * The editable surface, and the whitelist the save controller enforces.
     * A core_config path not listed here cannot be written from the browser.
     *
     * @return array<string, array{label: string, group: string, type: string, help: string}>
     */
    public static function editablePaths(): array
    {
        return [
            'general/business/name' => [
                'label' => 'Business name', 'group' => 'Business', 'type' => 'text',
                'help'  => 'Shown in the header, the footer and email subjects.',
            ],
            'general/business/gstin' => [
                'label' => 'GSTIN', 'group' => 'Business', 'type' => 'text',
                'help'  => 'Printed in the footer. Leave blank to hide it.',
            ],
            'general/contact/phone' => [
                'label' => 'Phone number', 'group' => 'Contact', 'type' => 'text',
                'help'  => 'Shown in the header and footer, and used for the call button.',
            ],
            'general/contact/whatsapp' => [
                'label' => 'WhatsApp number', 'group' => 'Contact', 'type' => 'text',
                'help'  => 'Used to build wa.me links. Include the country code.',
            ],
            'general/contact/email' => [
                'label' => 'Public email address', 'group' => 'Contact', 'type' => 'email',
                'help'  => 'Shown on the contact page and in the footer.',
            ],
            'general/contact/address' => [
                'label' => 'Postal address', 'group' => 'Contact', 'type' => 'textarea',
                'help'  => '',
            ],
            'general/contact/hours' => [
                'label' => 'Opening hours', 'group' => 'Contact', 'type' => 'text',
                'help'  => 'e.g. Mon to Sat, 9am to 7pm.',
            ],
            'general/social/facebook' => [
                'label' => 'Facebook page URL', 'group' => 'Social', 'type' => 'text', 'help' => '',
            ],
            'general/social/instagram' => [
                'label' => 'Instagram profile URL', 'group' => 'Social', 'type' => 'text', 'help' => '',
            ],
            'lead/notification/recipients' => [
                'label' => 'Send new enquiries to', 'group' => 'Enquiries', 'type' => 'email',
                'help'  => 'One or more email addresses, separated by commas.',
            ],
            'lead/notification/enabled' => [
                'label' => 'Email me about new enquiries', 'group' => 'Enquiries', 'type' => 'toggle',
                'help'  => 'Turning this off does not stop enquiries being saved.',
            ],
        ];
    }

    /** @return array<string, array<string, array{label: string, type: string, help: string, value: string, placeholder: bool}>> */
    public function getGroupedSettings(): array
    {
        $grouped = [];

        foreach (self::editablePaths() as $path => $meta) {
            $grouped[$meta['group']][$path] = [
                'label'       => $meta['label'],
                'type'        => $meta['type'],
                'help'        => $meta['help'],
                'value'       => SettingsModel::get($path),
                'placeholder' => SettingsModel::isPlaceholder($path),
            ];
        }

        return $grouped;
    }

    public function getSaveUrl(): string
    {
        return $this->getUrl('settings');
    }

    public function getPlaceholderCount(): int
    {
        $count = 0;
        foreach (array_keys(self::editablePaths()) as $path) {
            if (SettingsModel::isPlaceholder($path)) {
                $count++;
            }
        }
        return $count;
    }
}
