<?php

declare(strict_types=1);

namespace EcoLife\Backend\Controller\Adminhtml\Settings;

use EcoLife\Backend\App\Action\AbstractAction;
use EcoLife\Backend\Block\Adminhtml\Settings;
use EcoLife\Core\App\Logger;
use EcoLife\Core\Controller\ResultInterface;
use EcoLife\Core\Model\Settings as SettingsModel;
use Throwable;

/**
 * Edits core_config.
 *
 * This screen is what makes the whole architecture survivable for the family.
 * They cannot edit a template, so every contact detail on the public site has
 * to be reachable from here -- otherwise a wrong phone number is a developer
 * ticket, and it will sit wrong for weeks.
 */
final class Index extends AbstractAction
{
    protected function execute(): ResultInterface
    {
        if ($this->getRequest()->isPost()) {
            return $this->save();
        }

        return $this->resultPage()
            ->setTitle('Settings | EcoLifeSolar admin')
            ->setBodyClass('page-admin-settings')
            ->setContent(Settings::class, 'EcoLife_Backend::settings.phtml');
    }

    private function save(): ResultInterface
    {
        $messages = $this->context->getMessages();
        $back     = $this->context->getUrl()->getUrl('settings');
        $submitted = (array) $this->getRequest()->getPost('setting', []);

        $saved = 0;

        try {
            // Only paths this screen knows about are writable. Without the
            // whitelist, a crafted POST could create arbitrary config rows.
            foreach (Settings::editablePaths() as $path => $meta) {
                if (!array_key_exists($path, $submitted)) {
                    continue;
                }

                $value = trim((string) $submitted[$path]);

                if (($meta['type'] ?? 'text') === 'email' && $value !== ''
                    && !str_contains($path, 'recipients')
                    && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $messages->error(sprintf('"%s" is not a valid email address.', $meta['label']));
                    return $this->resultRedirect()->setUrl($back);
                }

                SettingsModel::save($path, $value !== '' ? mb_substr($value, 0, 65535) : null);
                $saved++;
            }
        } catch (Throwable $e) {
            Logger::exception($e);
            $messages->error('Could not save those settings.');
            return $this->resultRedirect()->setUrl($back);
        }

        Logger::info('Settings updated', ['count' => $saved, 'by' => $this->getCurrentUser()?->getUsername()]);
        $messages->success(sprintf('%d setting(s) saved. The public site is updated immediately.', $saved));

        return $this->resultRedirect()->setUrl($back);
    }
}
