<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Http;

class Response implements ResponseInterface
{
    /**
     * @param array<string, string|string[]> $headers
     */
    public function __construct(
        private int $statusCode = 200,
        private array $headers = [],
        private string $body = ''
    ) {
        $this->headers = $this->normalizeHeaders($headers);
    }

    public static function create(int $statusCode = 200, array $headers = [], string $body = ''): static
    {
        return new self($statusCode, $headers, $body);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function withStatus(int $statusCode): static
    {
        $clone = clone $this;
        $clone->statusCode = $statusCode;

        return $clone;
    }

    public function withHeader(string $name, string $value): static
    {
        $clone = clone $this;
        $clone->headers[(string) $name] = $value;

        return $clone;
    }

    public function withHeaders(array $headers): static
    {
        $clone = clone $this;
        $clone->headers = $this->normalizeHeaders($headers);

        return $clone;
    }

    public function withBody(string $body): static
    {
        $clone = clone $this;
        $clone->body = $body;

        return $clone;
    }

    public function getHeader(string $name, ?string $default = null): ?string
    {
        $lowerName = strtolower($name);

        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $lowerName) {
                if (is_array($value)) {
                    return (string) reset($value);
                }

                return (string) $value;
            }
        }

        return $default;
    }

    /**
     * @param array<string, string|string[]> $headers
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $value) {
            $normalized[(string) $name] = $value;
        }

        return $normalized;
    }
}

