<?php

declare(strict_types=1);

namespace EcoLife\Mail\Model;

use EcoLife\Core\App\Context;
use EcoLife\Core\App\Logger;
use EcoLife\Core\View\Element\Template;

/**
 * "Here is a link to set a new password."
 *
 * Takes primitives rather than an AdminUser, so EcoLife_Mail keeps knowing
 * nothing about EcoLife_Backend. The dependency runs one way: Backend asks Mail
 * to send something, and Mail is handed everything it needs to do that.
 *
 * No Reply-To. A reply to this would be a person asking a mailbox to reset
 * their password, which is exactly the conversation that must not happen over
 * email.
 */
final class PasswordResetNotification
{
    public function __construct(private readonly Context $context)
    {
    }

    public function send(string $email, string $name, string $resetUrl, int $validMinutes): bool
    {
        $block = new Template($this->context, [
            'name'           => $name,
            'reset_url'      => $resetUrl,
            'valid_minutes'  => $validMinutes,
        ]);

        $body = $block->setTemplate('EcoLife_Mail::email/password-reset.phtml')->toHtml();

        $sent = (new Transport())->send([$email], 'Reset your EcoLifeSolar admin password', $body);

        // The address is deliberately absent from the log line. Who asked is in
        // admin_password_reset.user_id already, and repeating the mailbox into a
        // file that is easier to read than the database buys nothing.
        Logger::info('Password reset email dispatched', ['sent' => $sent]);

        return $sent;
    }
}
