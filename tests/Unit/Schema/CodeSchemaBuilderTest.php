<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Tests\Unit\Schema;

use GraphQL\Type\Definition\Type;
use InvalidArgumentException;
use PFinalClub\WorkermanGraphQL\Exception\SchemaException;
use PFinalClub\WorkermanGraphQL\Schema\CodeSchemaBuilder;
use PFinalClub\WorkermanGraphQL\Schema\TypeRegistry;
use PHPUnit\Framework\TestCase;

final class CodeSchemaBuilderTest extends TestCase
{
    private CodeSchemaBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new CodeSchemaBuilder();
    }

    public function testCanAddQuery(): void
    {
        $this->builder->addQuery('hello', [
            'type' => Type::nonNull(Type::string()),
            'resolve' => fn() => 'Hello World',
        ]);

        $schema = $this->builder->build();

        $this->assertNotNull($schema->getQueryType());
        $this->assertTrue($schema->getQueryType()->hasField('hello'));
    }

    public function testCanAddMutation(): void
    {
        $this->builder->addQuery('dummy', [
            'type' => Type::string(),
            'resolve' => fn() => null,
        ]);

        $this->builder->addMutation('createUser', [
            'type' => Type::string(),
            'resolve' => fn() => 'user created',
        ]);

        $schema = $this->builder->build();

        $this->assertNotNull($schema->getMutationType());
        $this->assertTrue($schema->getMutationType()->hasField('createUser'));
    }

    public function testCanAddSubscription(): void
    {
        $this->builder->addQuery('dummy', [
            'type' => Type::string(),
            'resolve' => fn() => null,
        ]);

        $this->builder->addSubscription('userUpdated', [
            'type' => Type::string(),
            'resolve' => fn() => 'user updated',
        ]);

        $schema = $this->builder->build();

        $this->assertNotNull($schema->getSubscriptionType());
        $this->assertTrue($schema->getSubscriptionType()->hasField('userUpdated'));
    }

    public function testThrowsExceptionWhenNoQueries(): void
    {
        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('GraphQL schema must define at least one query field.');

        $this->builder->build();
    }

    public function testThrowsExceptionWhenTypeIsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "hello" must define a "type".');

        $this->builder->addQuery('hello', [
            'resolve' => fn() => 'Hello',
        ]);
    }

    public function testThrowsExceptionWhenTypeIsNotGraphQLType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->builder->addQuery('hello', [
            'type' => 'string',
            'resolve' => fn() => 'Hello',
        ]);
    }

    public function testThrowsExceptionWhenResolveIsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "hello" must define a callable "resolve" function.');

        $this->builder->addQuery('hello', [
            'type' => Type::string(),
        ]);
    }

    public function testCanAddQueryWithArgs(): void
    {
        $this->builder->addQuery('greet', [
            'type' => Type::nonNull(Type::string()),
            'args' => [
                'name' => ['type' => Type::string()],
            ],
            'resolve' => fn($root, array $args) => 'Hello ' . ($args['name'] ?? 'World'),
        ]);

        $schema = $this->builder->build();

        $this->assertTrue($schema->getQueryType()->hasField('greet'));
        $field = $schema->getQueryType()->getField('greet');
        // FieldDefinition has args property as FieldArgument array
        $hasNameArg = false;
        if (isset($field->args) && is_iterable($field->args)) {
            foreach ($field->args as $arg) {
                if (is_object($arg) && property_exists($arg, 'name') && $arg->name === 'name') {
                    $hasNameArg = true;
                    break;
                }
            }
        }
        $this->assertTrue($hasNameArg, 'Field should have "name" argument');
    }

    public function testCanRegisterType(): void
    {
        $registry = new TypeRegistry();
        $builder = new CodeSchemaBuilder($registry);

        $builder->registerType('CustomString', Type::string());
        $this->assertTrue($registry->has('CustomString'));
    }

    public function testCanReset(): void
    {
        $this->builder->addQuery('hello', [
            'type' => Type::string(),
            'resolve' => fn() => 'Hello',
        ]);

        $this->builder->reset();

        $this->expectException(SchemaException::class);
        $this->builder->build();
    }
}

