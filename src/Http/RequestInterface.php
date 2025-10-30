<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Http;

interface RequestInterface
{
    public function getMethod(): string;

    public function getPath(): string;

    /**
     * 返回原始请求体。
     */
    public function getBody(): string;

    /**
     * 返回解析后的请求体数据。
     *
     * @return array<string, mixed>|null
     */
    public function getParsedBody(): ?array;

    /**
     * @param array<string, mixed>|null $data
     */
    public function withParsedBody(?array $data): static;

    /**
     * @return array<string, mixed>
     */
    public function getQueryParams(): array;

    /**
     * @param array<string, mixed> $query
     */
    public function withQueryParams(array $query): static;

    /**
     * @return array<string, string|string[]>
     */
    public function getHeaders(): array;

    public function getHeader(string $name, ?string $default = null): ?string;

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array;

    public function getAttribute(string $name, mixed $default = null): mixed;

    public function withAttribute(string $name, mixed $value): static;
}

