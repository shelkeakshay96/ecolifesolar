<?php

declare(strict_types=1);

namespace EcoLife\Backend\Model;

use EcoLife\Core\App\Context;
use EcoLife\Core\App\Logger;

/**
 * The admin authentication session.
 *
 * Two details worth stating outright:
 *
 * The session is bound to a hash of the User-Agent, not the IP address.
 * Indian mobile networks rotate IPs mid-session, so IP binding would sign the
 * family out several times a day and teach them to ignore it.
 *
 * Login failure messages and timings are identical for "no such user" and
 * "wrong password". Distinguishing them turns the login form into a directory
 * of valid usernames.
 */
final class Auth
{
    private const KEY_USER_ID    = 'admin_user_id';
    private const KEY_LAST_SEEN  = 'admin_last_seen';
    private const KEY_AGENT_HASH = 'admin_agent_hash';

    public const IDLE_TIMEOUT = 900;   // 15 minutes

    private ?AdminUser $user = null;

    public function __construct(private readonly Context $context)
    {
    }

    public function isLoggedIn(): bool
    {
        $session = $this->context->getSession();
        $userId  = $session->get(self::KEY_USER_ID);

        if ($userId === null) {
            return false;
        }

        if ($session->get(self::KEY_AGENT_HASH) !== $this->agentHash()) {
            Logger::warning('Admin session rejected: user agent changed', ['user_id' => $userId]);
            $this->logout();
            return false;
        }

        $lastSeen = (int) $session->get(self::KEY_LAST_SEEN, 0);
        if ($lastSeen > 0 && time() - $lastSeen > self::IDLE_TIMEOUT) {
            $this->logout();
            return false;
        }

        $session->set(self::KEY_LAST_SEEN, time());

        return $this->getUser() !== null;
    }

    public function getUser(): ?AdminUser
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $userId = $this->context->getSession()->get(self::KEY_USER_ID);
        if ($userId === null) {
            return null;
        }

        $user = (new AdminUser())->load((int) $userId);

        // Deactivating an account must take effect on the next request, not at
        // the next login.
        if ($user->getId() === null || !$user->isActive()) {
            $this->logout();
            return null;
        }

        return $this->user = $user;
    }

    /**
     * @return array{0: bool, 1: string} success, and the message to show on failure
     */
    public function login(string $username, string $password): array
    {
        $generic = 'Those details are not correct.';

        $user = (new AdminUser())->loadByUsername($username);
        $ip   = $this->context->getRequest()->getClientIp();

        if ($user->getId() === null) {
            // Still run a verify so the response time matches the real path.
            $user->verifyPassword($password);
            Logger::warning('Admin login failed: no such user', ['username' => $username, 'ip' => $ip]);
            return [false, $generic];
        }

        if ($user->isLocked()) {
            Logger::warning('Admin login blocked: account locked', ['username' => $username, 'ip' => $ip]);
            return [false, sprintf(
                'This account is locked for another %d minute(s) after repeated failed attempts.',
                $user->getLockMinutesRemaining()
            )];
        }

        if (!$user->isActive()) {
            Logger::warning('Admin login failed: inactive account', ['username' => $username, 'ip' => $ip]);
            return [false, $generic];
        }

        if (!$user->verifyPassword($password)) {
            $user->registerFailure();
            Logger::warning('Admin login failed: wrong password', ['username' => $username, 'ip' => $ip]);
            return [false, $generic];
        }

        $session = $this->context->getSession();
        $session->regenerateId();
        $session->set(self::KEY_USER_ID, (int) $user->getId());
        $session->set(self::KEY_LAST_SEEN, time());
        $session->set(self::KEY_AGENT_HASH, $this->agentHash());

        $user->registerSuccess($ip);
        $this->user = $user;

        Logger::info('Admin login', ['username' => $username, 'ip' => $ip]);

        return [true, ''];
    }

    public function logout(): void
    {
        $session = $this->context->getSession();
        $session->unset(self::KEY_USER_ID);
        $session->unset(self::KEY_LAST_SEEN);
        $session->unset(self::KEY_AGENT_HASH);
        $session->regenerateId();
        $this->user = null;
    }

    private function agentHash(): string
    {
        return hash('sha256', $this->context->getRequest()->getUserAgent());
    }
}
