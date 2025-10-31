<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Tests\Unit;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use PFinalClub\WorkermanGraphQL\GraphQLEngine;
use PFinalClub\WorkermanGraphQL\Http\Request;
use PHPUnit\Framework\TestCase;

final class GraphQLEngineCacheTest extends TestCase
{
    private function createTestSchema(): Schema
    {
        return new Schema(
            (new SchemaConfig())
                ->setQuery(new \GraphQL\Type\Definition\ObjectType([
                    'name' => 'Query',
                    'fields' => [
                        'hello' => [
                            'type' => Type::nonNull(Type::string()),
                            'resolve' => fn() => 'Hello World',
                        ],
                    ],
                ]))
        );
    }

    public function testSchemaIsCached(): void
    {
        $factoryCallCount = 0;
        $engine = new GraphQLEngine();
        $engine->setSchemaFactory(function () use (&$factoryCallCount) {
            $factoryCallCount++;

            return $this->createTestSchema();
        });

        $request = new Request(
            'POST',
            '/graphql',
            ['Content-Type' => 'application/json'],
            '{"query": "{ hello }"}',
            ['query' => '{ hello }']
        );

        // 第一次调用，factory 应该执行
        $engine->handle($request);
        $this->assertEquals(1, $factoryCallCount);

        // 第二次调用，factory 不应该再执行（使用缓存）
        $engine->handle($request);
        $this->assertEquals(1, $factoryCallCount);
    }

    public function testSchemaCacheCanBeCleared(): void
    {
        $factoryCallCount = 0;
        $engine = new GraphQLEngine();
        $engine->setSchemaFactory(function () use (&$factoryCallCount) {
            $factoryCallCount++;

            return $this->createTestSchema();
        });

        $request = new Request(
            'POST',
            '/graphql',
            ['Content-Type' => 'application/json'],
            '{"query": "{ hello }"}',
            ['query' => '{ hello }']
        );

        // 第一次调用
        $engine->handle($request);
        $this->assertEquals(1, $factoryCallCount);

        // 清除缓存
        $engine->clearSchemaCache();

        // 再次调用，factory 应该再次执行
        $engine->handle($request);
        $this->assertEquals(2, $factoryCallCount);
    }

    public function testSchemaCacheTTL(): void
    {
        $factoryCallCount = 0;
        $engine = new GraphQLEngine();
        $engine->setSchemaCacheTTL(1); // 1秒TTL
        $engine->setSchemaFactory(function () use (&$factoryCallCount) {
            $factoryCallCount++;

            return $this->createTestSchema();
        });

        $request = new Request(
            'POST',
            '/graphql',
            ['Content-Type' => 'application/json'],
            '{"query": "{ hello }"}',
            ['query' => '{ hello }']
        );

        // 第一次调用
        $engine->handle($request);
        $this->assertEquals(1, $factoryCallCount);

        // 等待缓存过期
        sleep(2);

        // 再次调用，factory 应该再次执行（缓存已过期）
        $engine->handle($request);
        $this->assertEquals(2, $factoryCallCount);
    }
}

