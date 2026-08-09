<?php

declare(strict_types=1);

namespace EcoLife\Backend\Model;

use EcoLife\Backend\Model\ResourceModel\AdminUser as AdminUserResource;
use EcoLife\Core\Model\AbstractModel;
use EcoLife\Core\Model\ResourceModel\AbstractResource;

/**
 * An administrator or staff member.
 *
 * Login throttling lives on the row rather than in the session, because a
 * session is something the attacker controls: clearing a cookie must not clear
 * the failure count.
 */
final class AdminUser extends AbstractModel
{
    public const MAX_FAILURES   = 5;
    public const LOCK_MINUTES   = 15;
    public const WINDOW_MINUTES = 15;

    protected string $idFieldName = 'user_id';

    public function getResource(): AbstractResource
    {
        return $this->resource ??= new AdminUserResource();
    }

    public function getUsername(): string
    {
        return (string) $this->getData('username');
    }

    public function getEmail(): string
    {
        return (string) $this->getData('email');
    }

    public function getRole(): string
    {
        return (string) $this->getData('role', 'staff');
    }

    public function isAdmin(): bool
    {
        return $this->getRole() === 'admin';
    }

    public function isActive(): bool
    {
        return (int) $this->getData('is_active') === 1;
    }

    public function getDisplayName(): string
    {
        $name = trim((string) $this->getData('first_name') . ' ' . (string) $this->getData('last_name'));
        return $name !== '' ? $name : $this->getUsername();
    }

    public function loadByUsername(string $username): static
    {
        return $this->load($username, 'username');
    }

    public function verifyPassword(string $password): bool
    {
        $hash = (string) $this->getData('password_hash');

        // Always run the hash comparison, even when the row does not exist, so
        // that an unknown username and a wrong password take the same time.
        if ($hash === '') {
            password_verify($password, '$2y$12$' . str_repeat('.', 53));
            return false;
        }

        return password_verify($password, $hash);
    }

    public function setPassword(string $password): static
    {
        return $this->setData('password_hash', password_hash($password, PASSWORD_DEFAULT));
    }

    public function isLocked(): bool
    {
        $expires = $this->getData('lock_expires');
        return $expires !== null && strtotime((string) $expires) > time();
    }

    public function getLockMinutesRemaining(): int
    {
        $expires = $this->getData('lock_expires');
        if ($expires === null) {
            return 0;
        }
        return max(0, (int) ceil((strtotime((string) $expires) - time()) / 60));
    }

    /**
     * Record a failed attempt, locking the account once the threshold is
     * reached inside the window. The window resets on its own, so a stray
     * typo months ago does not count towards today's total.
     */
    public function registerFailure(): void
    {
        $failures     = (int) $this->getData('failures_num');
        $firstFailure = $this->getData('first_failure');

        $windowExpired = $firstFailure === null
            || strtotime((string) $firstFailure) < time() - (self::WINDOW_MINUTES * 60);

        if ($windowExpired) {
            $failures = 0;
            $this->setData('first_failure', date('Y-m-d H:i:s'));
        }

        $failures++;
        $this->setData('failures_num', $failures);

        if ($failures >= self::MAX_FAILURES) {
            $this->setData('lock_expires', date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60));
        }

        $this->save();
    }

    public function registerSuccess(string $ip): void
    {
        $this->addData([
            'failures_num'  => 0,
            'first_failure' => null,
            'lock_expires'  => null,
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $ip,
        ])->save();
    }
}
