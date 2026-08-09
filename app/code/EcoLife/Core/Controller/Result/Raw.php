<?php

declare(strict_types=1);

namespace EcoLife\Core\Controller\Result;

use EcoLife\Core\App\Response;
use EcoLife\Core\Controller\ResultInterface;

/**
 * A body and a content type, nothing else. Used for CSV export and for the
 * plain-text checks in early build steps.
 */
final class Raw implements ResultInterface
{
    private string $contents = '';
    private string $contentType = 'text/plain; charset=UTF-8';
    private int $statusCode = 200;

    /** @var array<string, string> */
    private array $headers = [];

    public function setContents(string $contents): self
    {
        $this->contents = $contents;
        return $this;
    }

    public function setContentType(string $contentType): self
    {
        $this->contentType = $contentType;
        return $this;
    }

    public function setHttpResponseCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /** Convenience for CSV export in the admin lead grid. */
    public function setDownload(string $filename): self
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?? 'download';
        return $this->setHeader('Content-Disposition', 'attachment; filename="' . $safe . '"');
    }

    public function renderResult(Response $response): void
    {
        $response->setHttpResponseCode($this->statusCode)
                 ->setHeader('Content-Type', $this->contentType);

        foreach ($this->headers as $name => $value) {
            $response->setHeader($name, $value);
        }

        $response->setBody($this->contents);
    }
}
