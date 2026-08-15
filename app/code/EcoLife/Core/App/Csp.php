<?php

declare(strict_types=1);

namespace EcoLife\Core\App;

/**
 * The per-request Content-Security-Policy nonce.
 *
 * It exists for exactly one thing: structured data. Google reads JSON-LD from
 * a <script type="application/ld+json"> element, and CSP does not care that the
 * element contains data rather than code -- script-src governs every <script>
 * tag, so under "script-src 'self'" the block is dropped and the site ships no
 * structured data at all, silently. There is no src= alternative; ld+json must
 * be inline to be read.
 *
 * The three ways out are 'unsafe-inline' (which turns the policy off for the
 * whole site to publish an address and a phone number), a hash (which means
 * recomputing a digest every time the markup changes), and a nonce. A nonce it
 * is.
 *
 * Both readers -- the template that stamps the attribute and Response, which
 * writes the header -- call value(), and the first of them to ask generates it.
 * That is why order does not matter here: whichever runs first fixes the value
 * for the rest of the request, and PHP's request lifetime is the scope.
 *
 * Fresh per request, and never reused: a nonce an attacker can predict is a
 * nonce that grants them the inline script it was meant to withhold.
 */
final class Csp
{
    private static ?string $nonce = null;

    public function __construct()
    {
        // Static by design; no instance carries state worth having.
    }

    public static function value(): string
    {
        return self::$nonce ??= base64_encode(random_bytes(16));
    }

    /** For the nonce attribute of a script tag. */
    public static function attribute(): string
    {
        return self::value();
    }

    /** Test seam. Nothing in the application calls this. */
    public static function reset(): void
    {
        self::$nonce = null;
    }
}
