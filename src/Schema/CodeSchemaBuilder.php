<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Schema;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema as GraphQLSchema;
use InvalidArgumentException;
use RuntimeException;

final class CodeSchemaBuilder implements SchemaBuilderInterface
{
    private TypeRegistry $typeRegistry;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $queries = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $mutations = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $subscriptions = [];

    public function __construct(?TypeRegistry $typeRegistry = null)
    {
        $this->typeRegistry = $typeRegistry ?? new TypeRegistry();
    }

    public function getTypeRegistry(): TypeRegistry
    {
        return $this->typeRegistry;
    }

    public function registerType(string $name, Type $type): self
    {
        $this->typeRegistry->register($name, $type);

        return $this;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function addQuery(string $name, array $config): self
    {
        $this->queries[$name] = $this->normalizeFieldConfig($name, $config);

        return $this;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function addMutation(string $name, array $config): self
    {
        $this->mutations[$name] = $this->normalizeFieldConfig($name, $config);

        return $this;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function addSubscription(string $name, array $config): self
    {
        $this->subscriptions[$name] = $this->normalizeFieldConfig($name, $config);

        return $this;
    }

    public function build(): GraphQLSchema
    {
        if (empty($this->queries)) {
            throw new RuntimeException('GraphQL schema must define at least one query field.');
        }

        $schemaConfig = [
            'query' => $this->createObjectType('Query', $this->queries),
        ];

        if (!empty($this->mutations)) {
            $schemaConfig['mutation'] = $this->createObjectType('Mutation', $this->mutations);
        }

        if (!empty($this->subscriptions)) {
            $schemaConfig['subscription'] = $this->createObjectType('Subscription', $this->subscriptions);
        }

        if (!empty($this->typeRegistry->all())) {
            $schemaConfig['typeLoader'] = fn(string $name): Type => $this->typeRegistry->get($name);
        }

        return new GraphQLSchema($schemaConfig);
    }

    public function reset(): void
    {
        $this->queries = [];
        $this->mutations = [];
        $this->subscriptions = [];
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     */
    private function createObjectType(string $name, array $fields): ObjectType
    {
        return new ObjectType([
            'name' => $name,
            'fields' => $fields,
        ]);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function normalizeFieldConfig(string $fieldName, array $config): array
    {
        if (!isset($config['type'])) {
            throw new InvalidArgumentException(sprintf('Field "%s" must define a "type".', $fieldName));
        }

        if (!($config['type'] instanceof Type)) {
            throw new InvalidArgumentException(sprintf('Field "%s" type must be an instance of %s.', $fieldName, Type::class));
        }

        if (!isset($config['resolve']) || !is_callable($config['resolve'])) {
            throw new InvalidArgumentException(sprintf('Field "%s" must define a callable "resolve" function.', $fieldName));
        }

        if (isset($config['args']) && !is_array($config['args'])) {
            throw new InvalidArgumentException(sprintf('Field "%s" args must be an array.', $fieldName));
        }

        if (!isset($config['description'])) {
            $config['description'] = null;
        }

        return $config;
    }
}

