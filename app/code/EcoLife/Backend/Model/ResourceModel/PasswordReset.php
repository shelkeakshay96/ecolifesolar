<?php

declare(strict_types=1);

namespace EcoLife\Backend\Model\ResourceModel;

use EcoLife\Core\Model\ResourceModel\AbstractResource;

final class PasswordReset extends AbstractResource
{
    protected string $table = 'admin_password_reset';
    protected string $idFieldName = 'reset_id';

    protected array $fields = [
        'reset_id', 'user_id', 'token_hash', 'expires_at', 'used_at',
        'requested_ip', 'created_at',
    ];

    /**
     * Burn every outstanding token for a user.
     *
     * Called when one of them is redeemed, so a second link sitting in an inbox
     * -- or in the inbox of whoever the first email was forwarded to -- stops
     * working the moment the password actually changes.
     *
     * Raw SQL because AbstractCollection has no bulk update, and looping models
     * to write one column would be several round trips to do one statement's
     * work.
     */
    public function invalidateAllFor(int $userId): void
    {
        $statement = $this->getConnection()->prepare(
            'UPDATE ' . $this->quotedTable() . '
                SET used_at = NOW()
              WHERE user_id = :user_id AND used_at IS NULL'
        );

        $statement->execute(['user_id' => $userId]);
    }

    /**
     * How many tokens a user has asked for in the last N minutes.
     *
     * The window is computed by MySQL, not by PHP, and that is not a style
     * choice. This database runs in UTC while PHP runs in IST, so a cutoff
     * built with date() sat five and a half hours ahead of every row that
     * CURRENT_TIMESTAMP had stamped -- the count came back zero every time and
     * the throttle silently never fired. Any comparison that puts a
     * PHP-generated timestamp next to a MySQL-generated one is wrong here.
     * Keep both ends on the same clock.
     */
    public function countRecentFor(int $userId, int $minutes): int
    {
        $statement = $this->getConnection()->prepare(
            'SELECT COUNT(*) FROM ' . $this->quotedTable() . '
              WHERE user_id = :user_id
                AND created_at >= NOW() - INTERVAL :minutes MINUTE'
        );

        $statement->execute(['user_id' => $userId, 'minutes' => $minutes]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Insert a token, with the expiry measured by the database.
     *
     * Written here rather than through AbstractModel::save() so expires_at can
     * be NOW() + INTERVAL rather than a PHP timestamp -- same reason as above.
     */
    public function insertToken(int $userId, string $tokenHash, int $validMinutes, ?string $ip): void
    {
        $statement = $this->getConnection()->prepare(
            'INSERT INTO ' . $this->quotedTable() . '
                 (user_id, token_hash, expires_at, requested_ip)
             VALUES (:user_id, :token_hash, NOW() + INTERVAL :minutes MINUTE, :ip)'
        );

        $statement->execute([
            'user_id'    => $userId,
            'token_hash' => $tokenHash,
            'minutes'    => $validMinutes,
            'ip'         => $ip,
        ]);
    }

    /**
     * The id of a live token: real, unspent and unexpired.
     *
     * All three conditions are in the WHERE clause so the expiry test happens
     * on the database's clock, and so a dead token is one indexed miss rather
     * than a row loaded and then rejected in PHP.
     */
    public function findLiveId(string $tokenHash): ?int
    {
        $statement = $this->getConnection()->prepare(
            'SELECT reset_id FROM ' . $this->quotedTable() . '
              WHERE token_hash = :token_hash
                AND used_at IS NULL
                AND expires_at > NOW()
              LIMIT 1'
        );

        $statement->execute(['token_hash' => $tokenHash]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * Housekeeping: drop rows that are spent or long expired.
     *
     * There is no cron in this application, so this is called opportunistically
     * whenever a token is issued. The table sees a handful of rows a year, so
     * the cost is nil and it never needs a scheduler to stay tidy.
     */
    public function purgeExpired(): void
    {
        $this->getConnection()->exec(
            'DELETE FROM ' . $this->quotedTable() . '
              WHERE expires_at < NOW() - INTERVAL 7 DAY'
        );
    }
}
