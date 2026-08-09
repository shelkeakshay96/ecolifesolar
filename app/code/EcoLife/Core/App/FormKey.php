<?php

declare(strict_types=1);

namespace EcoLife\Core\App;

/**
 * CSRF token, one per session per area.
 *
 * Validation happens in AbstractAction::dispatch() before execute() ever runs,
 * so a controller cannot forget to check it -- it would have to actively opt
 * out with CSRF_EXEMPT. Comparison is hash_equals, not ===, because a timing
 * oracle on a 64-character token is a real, if slow, attack.
 */
final class FormKey
{
    public const FIELD  = 'form_key';
    public const HEADER = 'X-Form-Key';

    public function __construct(private readonly Session $session)
    {
    }

    public function get(): string
    {
        $key = $this->session->get(self::FIELD);
        if (!is_string($key) || strlen($key) !== 64) {
            $key = bin2hex(random_bytes(32));
            $this->session->set(self::FIELD, $key);
        }
        return $key;
    }

    /**
     * Accepts the token from the POST body or from the X-Form-Key header, the
     * latter so AJAX submissions do not have to fake a form field.
     */
    public function isValid(Request $request): bool
    {
        $submitted = $request->getPost(self::FIELD) ?? $request->getHeader(self::HEADER);

        if (!is_string($submitted) || $submitted === '') {
            return false;
        }

        return hash_equals($this->get(), $submitted);
    }
}
