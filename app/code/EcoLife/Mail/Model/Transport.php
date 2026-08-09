<?php

declare(strict_types=1);

namespace EcoLife\Mail\Model;

use EcoLife\Core\App\Logger;
use EcoLife\Core\Model\Config;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

/**
 * Sends one message, through whichever transport env.php selects.
 *
 *   file  writes a .eml into var/log/mail/ and sends nothing. The local
 *         default: development must never be able to email a real customer.
 *   smtp  a real relay.
 *
 * PHPMailer is vendored under lib/internal, not installed by Composer. See
 * docs/vendored-libs.md for the version and the update procedure -- there is no
 * lockfile, so that document is the record.
 */
final class Transport
{
    /** @param list<string> $to */
    public function send(array $to, string $subject, string $htmlBody, ?string $replyTo = null): bool
    {
        $to = array_values(array_filter(array_map('trim', $to)));

        if ($to === []) {
            Logger::warning('Mail not sent: no recipients configured');
            return false;
        }

        return match ((string) Config::env('mail.transport', 'file')) {
            'smtp'  => $this->sendSmtp($to, $subject, $htmlBody, $replyTo),
            default => $this->writeFile($to, $subject, $htmlBody, $replyTo),
        };
    }

    /** @param list<string> $to */
    private function writeFile(array $to, string $subject, string $htmlBody, ?string $replyTo): bool
    {
        $dir = BP . '/var/log/mail';

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            Logger::error('Cannot create var/log/mail');
            return false;
        }

        $file = sprintf('%s/%s-%s.eml', $dir, date('Ymd-His'), bin2hex(random_bytes(3)));

        $headers = [
            'Date: ' . date('r'),
            'From: ' . $this->fromHeader(),
            'To: ' . implode(', ', $to),
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
        ];

        if ($replyTo !== null && $replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $written = @file_put_contents($file, implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody);

        if ($written === false) {
            Logger::error('Could not write ' . $file);
            return false;
        }

        Logger::info('Mail written to file transport', ['file' => basename($file), 'to' => $to]);

        return true;
    }

    /** @param list<string> $to */
    private function sendSmtp(array $to, string $subject, string $htmlBody, ?string $replyTo): bool
    {
        $mailer = new PHPMailer(true);

        try {
            $mailer->isSMTP();
            $mailer->Host       = (string) Config::env('mail.smtp.host');
            $mailer->Port       = (int) Config::env('mail.smtp.port', 587);
            $mailer->SMTPAuth   = true;
            $mailer->Username   = (string) Config::env('mail.smtp.user');
            $mailer->Password   = (string) Config::env('mail.smtp.password');
            $mailer->SMTPSecure = (string) Config::env('mail.smtp.encryption', 'tls');
            $mailer->CharSet    = 'UTF-8';
            $mailer->Timeout    = 15;

            $mailer->setFrom(
                (string) Config::env('mail.from.address'),
                (string) Config::env('mail.from.name', 'Eco Life')
            );

            foreach ($to as $recipient) {
                $mailer->addAddress($recipient);
            }

            if ($replyTo !== null && $replyTo !== '') {
                $mailer->addReplyTo($replyTo);
            }

            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body    = $htmlBody;
            $mailer->AltBody = trim(html_entity_decode(strip_tags($htmlBody), ENT_QUOTES, 'UTF-8'));

            $mailer->send();

            Logger::info('Mail sent over SMTP', ['to' => $to, 'subject' => $subject]);

            return true;
        } catch (PHPMailerException $e) {
            Logger::error('SMTP send failed: ' . $e->getMessage());
            return false;
        } catch (RuntimeException $e) {
            Logger::error('SMTP configuration error: ' . $e->getMessage());
            return false;
        }
    }

    private function fromHeader(): string
    {
        $address = (string) Config::env('mail.from.address', 'no-reply@localhost');
        $name    = (string) Config::env('mail.from.name', 'Eco Life');

        return sprintf('%s <%s>', $name, $address);
    }
}
