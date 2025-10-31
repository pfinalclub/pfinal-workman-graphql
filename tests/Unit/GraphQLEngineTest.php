<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Tests\Unit;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use PFinalClub\WorkermanGraphQL\Context;
use PFinalClub\WorkermanGraphQL\GraphQLEngine;
use PFinalClub\WorkermanGraphQL\Http\Request;
use PHPUnit\Framework\TestCase;

final class GraphQLEngineTest extends TestCase
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

    public function testCanHandleValidGraphQLRequest(): void
    {
        $engine = new GraphQLEngine($this->createTestSchema());
        $request = new Request(
            'POST',
            '/graphql',
            ['Content-Type' => 'application/json'],
            '{"query": "{ hello }"}',
            ['query' => '{ hello }']
        );

        $response = $engine->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('data', $body);
        $this->assertEquals(['hello' => 'Hello World'], $body['data']);
    }

    public function testReturnsErrorForInvalidRequest(): void
    {
        $engine = new GraphQLEngine($this->createTestSchema());
        $request = new Request('POST', '/graphql');

        $response = $engine->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('errors', $body);
    }
}

