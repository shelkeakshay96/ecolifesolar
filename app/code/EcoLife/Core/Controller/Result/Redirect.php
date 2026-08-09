<?php

declare(strict_types=1);

namespace EcoLife\Core\Controller\Result;

use EcoLife\Core\App\Response;
use EcoLife\Core\Controller\ResultInterface;

/**
 * A redirect, restricted to this application.
 *
 * setUrl() rejects anything with a scheme or a protocol-relative prefix. An
 * open redirect on a lead-capture site is a phishing gift, and the application
 * has no legitimate reason to bounce a visitor off-site.
 */
final class Redirect implements ResultInterface
{
    private string $url = '/';
    private int $statusCode = 302;

    public function setUrl(string $url): self
    {
        $this->url = self::sanitise($url);
        return $this;
    }

    public function setHttpResponseCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public static function sanitise(string $url): string
    {
        $url = trim($url);

        // Protocol-relative (//evil.example) and absolute (https://evil.example)
        // both leave the site; a control character can smuggle a header break.
        if ($url === '' || str_starts_with($url, '//') || preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
            return '/';
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return '/';
        }

        return str_starts_with($url, '/') ? $url : '/' . $url;
    }

    public function renderResult(Response $response): void
    {
        $response->setRedirect($this->url, $this->statusCode)->setBody('');
    }
}
