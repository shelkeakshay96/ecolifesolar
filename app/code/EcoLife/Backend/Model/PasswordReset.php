<?php

declare(strict_types=1);

namespace EcoLife\Backend\Model;

use EcoLife\Backend\Model\ResourceModel\PasswordReset as PasswordResetResource;
use EcoLife\Core\App\Logger;
use EcoLife\Core\Model\AbstractModel;
use EcoLife\Core\Model\ResourceModel\AbstractResource;

/**
 * A single-use, time-limited password reset token.
 *
 * The raw token leaves this class exactly once, as the return value of
 * issue(), and is never stored. Everything after that works from its sha256.
 */
final class PasswordReset extends AbstractModel
{
    /** Long enough that the link is useless by the time anyone finds the email. */
    public const VALID_MINUTES = 60;

    /** Requests allowed per account per window, before we quietly stop sending. */
    public const MAX_PER_WINDOW = 3;
    public const WINDOW_MINUTES = 15;

    protected string $idFieldName = 'reset_id';

    public function getResource(): AbstractResource
    {
        return $this->resource ??= new PasswordResetResource();
    }

    /**
     * Create a token for a user and return the RAW value, once.
     *
     * Returns null when the account has already asked too many times in the
     * window. The caller must not distinguish that from success in anything the
     * browser can see -- a throttle you can observe is a way to test whether an
     * address is registered.
     */
    public static function issue(int $userId, string $ip): ?string
    {
        $resource = new PasswordResetResource();
        $resource->purgeExpired();

        if ($resource->countRecentFor($userId, self::WINDOW_MINUTES) >= self::MAX_PER_WINDOW) {
            Logger::warning('Password reset throttled', ['user_id' => $userId, 'ip' => $ip]);
            return null;
        }

        $token = bin2hex(random_bytes(32));

        $resource->insertToken(
            $userId,
            self::hash($token),
            self::VALID_MINUTES,
            $ip !== '' ? substr($ip, 0, 45) : null
        );

        Logger::info('Password reset issued', ['user_id' => $userId, 'ip' => $ip]);

        return $token;
    }

    /**
     * The row for a raw token, if it is real, unused and unexpired.
     *
     * All three failures return null and are indistinguishable to the caller,
     * because they are indistinguishable to a legitimate user too: the link
     * does not work, and which of the three reasons applies is not information
     * worth handing to whoever is holding it.
     */
    public static function findValid(string $token): ?self
    {
        if ($token === '' || !ctype_xdigit($token) || strlen($token) !== 64) {
            return null;
        }

        // Real, unspent and unexpired are all decided by the database, in one
        // indexed lookup. Doing the expiry test in PHP would compare our clock
        // against a column MySQL wrote, which is exactly the mismatch that
        // silently disabled the throttle.
        $id = (new PasswordResetResource())->findLiveId(self::hash($token));

        if ($id === null) {
            return null;
        }

        $reset = (new self())->load($id);

        return $reset->getId() === null ? null : $reset;
    }

    /**
     * Spend this token, and every other one outstanding for the same user.
     *
     * Order matters: invalidateAllFor covers this row too, so a single
     * statement closes the whole set rather than leaving a race between
     * marking this one and clearing the rest.
     */
    public function consume(): void
    {
        $this->getResource()->invalidateAllFor((int) $this->getData('user_id'));
    }

    public function getUser(): AdminUser
    {
        return (new AdminUser())->load((int) $this->getData('user_id'));
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
