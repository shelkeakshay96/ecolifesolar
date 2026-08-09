<?php

declare(strict_types=1);

namespace EcoLife\Core\App;

/**
 * One HTTP request, read once from the superglobals and then treated as data.
 *
 * Route parameters (the /key/value/ tail of a URL) are merged in by the router
 * after matching, which is why setParams() exists but there is no general
 * setter for the body or query.
 */
final class Request
{
    /** @var array<string, string> */
    private array $params = [];

    private function __construct(
        private string $method,
        private string $pathInfo,
        private readonly array $query,
        private readonly array $post,
        private readonly array $server,
        private readonly array $cookies,
        private readonly array $files,
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri  = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            '/' . trim(rawurldecode($path), '/'),
            $_GET,
            $_POST,
            $_SERVER,
            $_COOKIE,
            $_FILES
        );
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    /** Path with no query string, always leading-slashed, never trailing. */
    public function getPathInfo(): string
    {
        return $this->pathInfo;
    }

    /** Area::detect() rewrites this after stripping the admin front name. */
    public function setPathInfo(string $pathInfo): void
    {
        $this->pathInfo = '/' . trim($pathInfo, '/');
    }

    /** @return list<string> non-empty path segments */
    public function getSegments(): array
    {
        return array_values(array_filter(explode('/', $this->pathInfo), static fn($s) => $s !== ''));
    }

    /** Route params take precedence, then POST, then GET. */
    public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function getPost(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->post;
        }
        return $this->post[$key] ?? $default;
    }

    public function getQuery(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    /** @param array<string, string> $params */
    public function setParams(array $params): void
    {
        $this->params = $params + $this->params;
    }

    /** @return array<string, string> */
    public function getParams(): array
    {
        return $this->params;
    }

    public function getServer(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function getCookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function getFiles(?string $key = null): mixed
    {
        return $key === null ? $this->files : ($this->files[$key] ?? null);
    }

    public function getHeader(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $this->server[$key] ?? null;
        return is_string($value) ? $value : null;
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->getHeader('X-Requested-With')) === 'xmlhttprequest'
            || str_contains((string) $this->getHeader('Accept'), 'application/json');
    }

    public function isSecure(): bool
    {
        return ($this->server['HTTPS'] ?? 'off') !== 'off'
            || (int) ($this->server['SERVER_PORT'] ?? 80) === 443;
    }

    /**
     * The connecting address. Proxy headers are deliberately ignored: nothing
     * sits in front of this application yet, and trusting X-Forwarded-For
     * without a trusted-proxy list turns the per-IP submission cap into a
     * formality.
     */
    public function getClientIp(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '');
    }

    public function getUserAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function getHost(): string
    {
        return (string) ($this->server['HTTP_HOST'] ?? 'localhost');
    }

    public function getScheme(): string
    {
        return $this->isSecure() ? 'https' : 'http';
    }
}
