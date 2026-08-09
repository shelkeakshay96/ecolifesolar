<?php

declare(strict_types=1);

namespace EcoLife\Core\App;

/**
 * Flash messages, held in the session so they survive a redirect.
 *
 * Read is destructive: getAll() empties the store, so a message shown once is
 * gone. That is what makes post/redirect/get behave.
 */
final class Messages
{
    public const SUCCESS = 'success';
    public const ERROR   = 'error';
    public const WARNING = 'warning';
    public const NOTICE  = 'notice';

    private const KEY = 'messages';

    public function __construct(private readonly Session $session)
    {
    }

    public function add(string $type, string $text): void
    {
        $messages   = $this->session->get(self::KEY, []);
        $messages[] = ['type' => $type, 'text' => $text];
        $this->session->set(self::KEY, $messages);
    }

    public function success(string $text): void
    {
        $this->add(self::SUCCESS, $text);
    }

    public function error(string $text): void
    {
        $this->add(self::ERROR, $text);
    }

    public function warning(string $text): void
    {
        $this->add(self::WARNING, $text);
    }

    public function notice(string $text): void
    {
        $this->add(self::NOTICE, $text);
    }

    /** @return list<array{type: string, text: string}> */
    public function getAll(): array
    {
        $messages = $this->session->get(self::KEY, []);
        $this->session->unset(self::KEY);
        return is_array($messages) ? $messages : [];
    }

    public function hasMessages(): bool
    {
        return (bool) $this->session->get(self::KEY, []);
    }
}
