<?php

declare(strict_types=1);

namespace EcoLife\Core\Controller\Result;

use EcoLife\Core\App\Response;
use EcoLife\Core\Controller\ResultInterface;

/**
 * JSON response, for the AJAX lead submission and the calculator endpoint.
 */
final class Json implements ResultInterface
{
    private mixed $data = null;
    private int $statusCode = 200;

    public function setData(mixed $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function setHttpResponseCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /** @param array<string, string> $errors field => message */
    public function setError(string $message, array $errors = [], int $code = 422): self
    {
        $this->statusCode = $code;
        $this->data = array_filter([
            'success' => false,
            'message' => $message,
            'errors'  => $errors ?: null,
        ], static fn($v) => $v !== null);
        return $this;
    }

    public function setSuccess(string $message, array $extra = []): self
    {
        $this->statusCode = 200;
        $this->data = ['success' => true, 'message' => $message] + $extra;
        return $this;
    }

    public function renderResult(Response $response): void
    {
        $response->setHttpResponseCode($this->statusCode)
                 ->setHeader('Content-Type', 'application/json; charset=UTF-8')
                 ->setBody((string) json_encode(
                     $this->data,
                     JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                 ));
    }
}
