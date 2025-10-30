<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Schema;

use GraphQL\Type\Definition\Type;
use RuntimeException;

final class TypeRegistry
{
    /**
     * @var array<string, Type>
     */
    private array $types = [];

    public function has(string $name): bool
    {
        return isset($this->types[$name]);
    }

    public function get(string $name): Type
    {
        if (!$this->has($name)) {
            throw new RuntimeException(sprintf('Type "%s" is not registered.', $name));
        }

        return $this->types[$name];
    }

    public function register(string $name, Type $type): void
    {
        $this->types[$name] = $type;
    }

    /**
     * @return array<string, Type>
     */
    public function all(): array
    {
        return $this->types;
    }
}

