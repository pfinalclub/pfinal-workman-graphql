<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Tests\Unit\Schema;

use PFinalClub\WorkermanGraphQL\Exception\SchemaException;
use PFinalClub\WorkermanGraphQL\Schema\SdlSchemaBuilder;
use PHPUnit\Framework\TestCase;

final class SdlSchemaBuilderTest extends TestCase
{
    private SdlSchemaBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new SdlSchemaBuilder();
    }

    public function testCanBuildSchemaFromString(): void
    {
        $sdl = <<<'GRAPHQL'
type Query {
  hello: String!
}
GRAPHQL;

        $builder = $this->builder->fromString($sdl);
        $builder->setResolver('Query', 'hello', fn() => 'Hello World');

        $schema = $builder->build();

        $this->assertNotNull($schema->getQueryType());
        $this->assertTrue($schema->getQueryType()->hasField('hello'));
    }

    public function testCanBuildSchemaFromFile(): void
    {
        $schemaFile = __DIR__ . '/../../fixtures/schema.graphql';
        $this->createFixtureFile($schemaFile, <<<'GRAPHQL'
type Query {
  ping: String!
}
GRAPHQL);

        $builder = $this->builder->fromFile($schemaFile);
        $builder->setResolver('Query', 'ping', fn() => 'pong');

        $schema = $builder->build();

        $this->assertNotNull($schema->getQueryType());
        $this->assertTrue($schema->getQueryType()->hasField('ping'));
    }

    public function testThrowsExceptionWhenSdlIsEmpty(): void
    {
        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('SDL schema string is empty.');

        $this->builder->build();
    }

    public function testThrowsExceptionWhenFileDoesNotExist(): void
    {
        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('SDL file');

        $this->builder->fromFile('/nonexistent/file.graphql');
    }

    public function testCanSetMultipleResolvers(): void
    {
        $sdl = <<<'GRAPHQL'
type Query {
  hello: String!
  world: String!
}
GRAPHQL;

        $builder = $this->builder->fromString($sdl);
        $builder->setResolvers([
            'Query' => [
                'hello' => fn() => 'Hello',
                'world' => fn() => 'World',
            ],
        ]);

        $schema = $builder->build();

        $this->assertTrue($schema->getQueryType()->hasField('hello'));
        $this->assertTrue($schema->getQueryType()->hasField('world'));
    }

    public function testThrowsExceptionWhenResolverIsNotCallable(): void
    {
        $sdl = <<<'GRAPHQL'
type Query {
  hello: String!
}
GRAPHQL;

        $this->expectException(SchemaException::class);

        $this->builder->fromString($sdl)
            ->setResolvers([
                'Query' => [
                    'hello' => 'not callable',
                ],
            ]);
    }

    public function testCanSetTypeDecorator(): void
    {
        $sdl = <<<'GRAPHQL'
type Query {
  hello: String!
}
GRAPHQL;

        $decoratorCalled = false;
        $builder = $this->builder->fromString($sdl);
        $builder->setTypeDecorator(function ($typeConfig) use (&$decoratorCalled) {
            $decoratorCalled = true;

            return $typeConfig;
        });
        $builder->setResolver('Query', 'hello', fn() => 'Hello');

        $builder->build();

        $this->assertTrue($decoratorCalled);
    }

    private function createFixtureFile(string $path, string $content): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $content);
    }
}

