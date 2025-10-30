<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL;

use PFinalClub\WorkermanGraphQL\Http\RequestInterface;

final class Context
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(
        private RequestInterface $request,
        private array $values = []
    ) {
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    public function withRequest(RequestInterface $request): self
    {
        $clone = clone $this;
        $clone->request = $request;

        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function withValue(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->values[$key] = $value;

        return $clone;
    }
}

