<?php

declare(strict_types=1);

namespace EcoLife\Lead\Model;

use EcoLife\Core\App\Context;
use EcoLife\Core\App\Logger;
use EcoLife\Core\Model\Db;
use Throwable;

/**
 * Three cheap anti-spam controls, none of which asks a real customer to do
 * anything.
 *
 *   Honeypot        a field a human never sees and a bot fills in.
 *   Render timing   a signed timestamp; humans do not complete a form in
 *                   under three seconds.
 *   Per-IP cap      three submissions an hour from one address.
 *
 * A honeypot hit is answered with the ordinary success response and nothing is
 * written. Telling a bot it was detected only teaches whoever wrote it to try
 * the next thing.
 */
final class SpamGuard
{
    public const HONEYPOT_FIELD  = 'website';
    public const TIMESTAMP_FIELD = 'rendered_at';

    private const MIN_SECONDS   = 3;
    private const MAX_AGE       = 7200;   // a form left open for 2 hours is stale
    private const MAX_PER_HOUR  = 3;

    public function __construct(private readonly Context $context)
    {
    }

    /** A signed timestamp for the form to carry. */
    public function issueTimestamp(): string
    {
        $now = (string) time();
        return $now . '.' . $this->sign($now);
    }

    public function honeypotTripped(): bool
    {
        $value = $this->context->getRequest()->getPost(self::HONEYPOT_FIELD, '');
        return is_string($value) && trim($value) !== '';
    }

    /**
     * @return string|null null when acceptable, otherwise the reason
     */
    public function rejectionReason(): ?string
    {
        $submitted = (string) $this->context->getRequest()->getPost(self::TIMESTAMP_FIELD, '');

        if (!str_contains($submitted, '.')) {
            return 'Please reload the page and try again.';
        }

        [$issued, $signature] = explode('.', $submitted, 2);

        if (!ctype_digit($issued) || !hash_equals($this->sign($issued), $signature)) {
            return 'Please reload the page and try again.';
        }

        $elapsed = time() - (int) $issued;

        if ($elapsed < self::MIN_SECONDS) {
            return 'That was submitted a little too quickly. Please try once more.';
        }

        if ($elapsed > self::MAX_AGE) {
            return 'This form has been open a while. Please reload the page and try again.';
        }

        if ($this->overRateLimit()) {
            return 'We have already received several enquiries from this connection. '
                 . 'Please call us instead and we will help straight away.';
        }

        return null;
    }

    /**
     * Counted from the lead table rather than the session, so clearing cookies
     * does not reset it.
     */
    private function overRateLimit(): bool
    {
        $ip = $this->context->getRequest()->getClientIp();

        if ($ip === '') {
            return false;
        }

        try {
            $statement = Db::instance()->prepare(
                'SELECT COUNT(*) FROM ' . Db::quoteIdentifier('lead') . '
                 WHERE ip_address = ? AND created_at >= NOW() - INTERVAL 1 HOUR'
            );
            $statement->execute([$ip]);

            return (int) $statement->fetchColumn() >= self::MAX_PER_HOUR;
        } catch (Throwable $e) {
            // A broken rate-limit query must not block a genuine enquiry.
            Logger::error('Rate limit check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Keyed on the session's form key, so a timestamp cannot be lifted from one
     * visitor's page and replayed by another.
     */
    private function sign(string $value): string
    {
        return hash_hmac('sha256', $value, $this->context->getFormKey()->get());
    }
}
