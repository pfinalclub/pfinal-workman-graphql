<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Http;

interface ResponseInterface
{
    public function getStatusCode(): int;

    /**
     * @return array<string, string|string[]>
     */
    public function getHeaders(): array;

    public function getBody(): string;

    public function withStatus(int $statusCode): static;

    public function withHeader(string $name, string $value): static;

    /**
     * @param array<string, string|string[]> $headers
     */
    public function withHeaders(array $headers): static;

    public function withBody(string $body): static;
}

