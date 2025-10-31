<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Schema;

use GraphQL\Type\Schema as GraphQLSchema;
use GraphQL\Utils\BuildSchema;
use PFinalClub\WorkermanGraphQL\Exception\SchemaException;

final class SdlSchemaBuilder implements SchemaBuilderInterface
{
    private string $sdl = '';

    /**
     * @var array<string, array<string, callable>>
     */
    private array $fieldResolvers = [];

    /**
     * @var callable|null
     */
    private $typeDecorator = null;

    public function fromString(string $sdl): self
    {
        $this->sdl = $sdl;

        return $this;
    }

    public function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new SchemaException(sprintf('SDL file "%s" does not exist.', $path));
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new SchemaException(sprintf('Failed to read SDL file "%s".', $path));
        }

        $this->sdl = $contents;

        return $this;
    }

    public function setResolver(string $typeName, string $fieldName, callable $resolver): self
    {
        $this->fieldResolvers[$typeName][$fieldName] = $resolver;

        return $this;
    }

    /**
     * @param array<string, array<string, callable>> $resolvers
     */
    public function setResolvers(array $resolvers): self
    {
        foreach ($resolvers as $type => $fields) {
            foreach ($fields as $field => $resolver) {
                if (!is_callable($resolver)) {
                    throw new SchemaException(sprintf('Resolver for %s.%s must be callable.', $type, $field));
                }

                $this->setResolver($type, (string) $field, $resolver);
            }
        }

        return $this;
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $decorator
     */
    public function setTypeDecorator(callable $decorator): self
    {
        $this->typeDecorator = $decorator;

        return $this;
    }

    public function build(): GraphQLSchema
    {
        if ($this->sdl === '') {
            throw new SchemaException('SDL schema string is empty.');
        }

        $fieldResolvers = $this->fieldResolvers;
        $typeDecorator = $this->typeDecorator;

        $decorator = static function (array $typeConfig) use ($fieldResolvers, $typeDecorator): array {
            $typeName = $typeConfig['name'] ?? null;

            if ($typeDecorator !== null) {
                $typeConfig = $typeDecorator($typeConfig);
            }

            if ($typeName !== null && isset($fieldResolvers[$typeName])) {
                $fields = $typeConfig['fields'];
                $fields = is_callable($fields) ? $fields() : $fields;

                foreach ($fieldResolvers[$typeName] as $fieldName => $resolver) {
                    if (isset($fields[$fieldName])) {
                        $fields[$fieldName]['resolve'] = $resolver;
                    }
                }

                $typeConfig['fields'] = static function () use ($fields): array {
                    return $fields;
                };
            }

            return $typeConfig;
        };

        return BuildSchema::build($this->sdl, $decorator);
    }
}

