<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Http;

final class Request implements RequestInterface
{
    /**
     * @param array<string, string|string[]> $headers
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed>|null $parsedBody
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        private string $method,
        private string $path,
        private array $headers = [],
        private string $body = '',
        private ?array $parsedBody = null,
        private array $queryParams = [],
        private array $attributes = []
    ) {
        $this->method = strtoupper($method);
        $this->headers = $this->normalizeHeaders($headers);
    }

    public static function create(array $data): self
    {
        return new self(
            $data['method'] ?? 'GET',
            $data['path'] ?? '/',
            $data['headers'] ?? [],
            (string) ($data['body'] ?? ''),
            $data['parsedBody'] ?? null,
            $data['queryParams'] ?? [],
            $data['attributes'] ?? []
        );
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getParsedBody(): ?array
    {
        return $this->parsedBody;
    }

    public function withParsedBody(?array $data): static
    {
        $clone = clone $this;
        $clone->parsedBody = $data;

        return $clone;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function withQueryParams(array $query): static
    {
        $clone = clone $this;
        $clone->queryParams = $query;

        return $clone;
    }

    public function getHeaders(): array
    {
        return $this->headers;
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

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute(string $name, mixed $value): static
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;

        return $clone;
    }

    public function withBody(string $body): static
    {
        $clone = clone $this;
        $clone->body = $body;

        return $clone;
    }

    public function withMethod(string $method): static
    {
        $clone = clone $this;
        $clone->method = strtoupper($method);

        return $clone;
    }

    public function withPath(string $path): static
    {
        $clone = clone $this;
        $clone->path = $path;

        return $clone;
    }

    /**
     * @param array<string, string|string[]> $headers
     */
    public function withHeaders(array $headers): static
    {
        $clone = clone $this;
        $clone->headers = $this->normalizeHeaders($headers);

        return $clone;
    }

    public function withHeader(string $name, string $value): static
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
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

