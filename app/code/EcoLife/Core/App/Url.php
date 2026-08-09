<?php

declare(strict_types=1);

namespace EcoLife\Core\App;

use EcoLife\Core\Model\Config;

/**
 * URL building. Area-aware, so a block never has to know whether it is being
 * rendered in the admin panel -- getUrl('leads/lead/view', ['id' => 5]) prefixes
 * the admin front name automatically when the current area is adminhtml.
 *
 * getStaticUrl() appends ?v=<mtime> so a rebuilt stylesheet is fetched fresh
 * without any cache-busting infrastructure.
 */
final class Url
{
    public function __construct(
        private readonly Request $request,
        private readonly Area $area,
    ) {
    }

    public function getBaseUrl(): string
    {
        return $this->request->getScheme() . '://' . $this->request->getHost() . '/';
    }

    /**
     * @param array<string, string|int> $params appended as /key/value/ pairs
     */
    public function getUrl(string $path = '', array $params = [], bool $absolute = false): string
    {
        $path = trim($path, '/');

        if ($this->area->isAdmin()) {
            $path = Config::adminFrontName() . ($path === '' ? '' : '/' . $path);
        }

        foreach ($params as $key => $value) {
            if ($key === '_query') {
                continue;
            }
            $path .= '/' . rawurlencode((string) $key) . '/' . rawurlencode((string) $value);
        }

        $url = '/' . $path;

        if (isset($params['_query']) && is_array($params['_query']) && $params['_query'] !== []) {
            $url .= '?' . http_build_query($params['_query']);
        }

        return $absolute ? rtrim($this->getBaseUrl(), '/') . $url : $url;
    }

    /** A URL in the other area -- used by the admin "view site" link. */
    public function getFrontendUrl(string $path = '', array $params = []): string
    {
        $path = trim($path, '/');
        foreach ($params as $key => $value) {
            $path .= '/' . rawurlencode((string) $key) . '/' . rawurlencode((string) $value);
        }
        return '/' . $path;
    }

    /**
     * Static asset under pub/, cache-busted by file modification time.
     * A missing file still produces a URL -- a 404 on a stylesheet is easier to
     * diagnose than a silently omitted link tag.
     */
    public function getStaticUrl(string $file): string
    {
        $file = ltrim($file, '/');
        $path = BP . '/pub/' . $file;
        $url  = '/' . $file;

        if (is_file($path)) {
            $url .= '?v=' . filemtime($path);
        }

        return $url;
    }

    public function getMediaUrl(string $file): string
    {
        return '/media/' . ltrim($file, '/');
    }

    public function getCurrentUrl(): string
    {
        return $this->request->getPathInfo();
    }
}
