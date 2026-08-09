<?php

declare(strict_types=1);

namespace EcoLife\Core\App;

/**
 * The response, buffered until send().
 *
 * send() is the single place that emits security headers. Scattering header()
 * calls through controllers is how a site ends up with CSP on four pages out of
 * six, so nothing else in the application is allowed to set them.
 */
final class Response
{
    private int $statusCode = 200;
    private string $body = '';

    /** @var array<string, string> */
    private array $headers = [];

    /** @var list<array{name: string, value: string, options: array}> */
    private array $cookies = [];

    private bool $sent = false;

    public function setHttpResponseCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function getHttpResponseCode(): int
    {
        return $this->statusCode;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function appendBody(string $body): self
    {
        $this->body .= $body;
        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setCookie(string $name, string $value, array $options = []): self
    {
        $this->cookies[] = ['name' => $name, 'value' => $value, 'options' => $options];
        return $this;
    }

    public function setRedirect(string $url, int $code = 302): self
    {
        return $this->setHttpResponseCode($code)->setHeader('Location', $url);
    }

    public function send(): void
    {
        if ($this->sent) {
            return;
        }
        $this->sent = true;

        if (PHP_SAPI === 'cli') {
            echo $this->body;
            return;
        }

        if (!headers_sent()) {
            http_response_code($this->statusCode);

            // array_merge, not +: with the union operator the LEFT operand wins
            // on a duplicate key, which would pin every response to the default
            // text/html and quietly mislabel JSON and CSV downloads.
            foreach (array_merge($this->securityHeaders(), $this->headers) as $name => $value) {
                header($name . ': ' . $value);
            }

            foreach ($this->cookies as $cookie) {
                setcookie($cookie['name'], $cookie['value'], $cookie['options'] + [
                    'path'     => '/',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
        }

        echo $this->body;
    }

    /**
     * Applied first, so an explicit setHeader() can still override one of these
     * when a controller genuinely needs to (a PDF download changing
     * Content-Disposition, for instance).
     *
     * @return array<string, string>
     */
    private function securityHeaders(): array
    {
        return [
            'Content-Type'           => 'text/html; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options'        => 'SAMEORIGIN',
            'Referrer-Policy'        => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => implode('; ', [
                "default-src 'self'",
                "img-src 'self' data:",
                "style-src 'self' 'unsafe-inline'",
                "script-src 'self'",
                "form-action 'self'",
                "frame-ancestors 'self'",
                "base-uri 'self'",
            ]),
        ];
    }
}
