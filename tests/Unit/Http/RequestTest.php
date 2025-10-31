<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Tests\Unit\Http;

use PFinalClub\WorkermanGraphQL\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testCanCreateRequest(): void
    {
        $request = new Request('GET', '/graphql');

        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('/graphql', $request->getPath());
        $this->assertEquals('', $request->getBody());
        $this->assertNull($request->getParsedBody());
        $this->assertEquals([], $request->getQueryParams());
    }

    public function testCanCreateRequestWithAllParameters(): void
    {
        $headers = ['Content-Type' => 'application/json'];
        $body = '{"query": "{ hello }"}';
        $parsedBody = ['query' => '{ hello }'];
        $queryParams = ['key' => 'value'];

        $request = new Request('POST', '/graphql', $headers, $body, $parsedBody, $queryParams);

        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/graphql', $request->getPath());
        $this->assertEquals($body, $request->getBody());
        $this->assertEquals($parsedBody, $request->getParsedBody());
        $this->assertEquals($queryParams, $request->getQueryParams());
    }

    public function testMethodIsNormalizedToUppercase(): void
    {
        $request = new Request('post', '/graphql');
        $this->assertEquals('POST', $request->getMethod());
    }

    public function testCanGetHeader(): void
    {
        $request = new Request('GET', '/graphql', ['Content-Type' => 'application/json']);

        $this->assertEquals('application/json', $request->getHeader('Content-Type'));
        $this->assertEquals('application/json', $request->getHeader('content-type'));
        $this->assertNull($request->getHeader('X-Custom-Header'));
        $this->assertEquals('default', $request->getHeader('X-Custom-Header', 'default'));
    }

    public function testCanUpdateParsedBody(): void
    {
        $request = new Request('POST', '/graphql');
        $newRequest = $request->withParsedBody(['query' => '{ hello }']);

        $this->assertNotSame($request, $newRequest);
        $this->assertNull($request->getParsedBody());
        $this->assertEquals(['query' => '{ hello }'], $newRequest->getParsedBody());
    }

    public function testCanUpdateQueryParams(): void
    {
        $request = new Request('GET', '/graphql');
        $newRequest = $request->withQueryParams(['key' => 'value']);

        $this->assertNotSame($request, $newRequest);
        $this->assertEquals([], $request->getQueryParams());
        $this->assertEquals(['key' => 'value'], $newRequest->getQueryParams());
    }

    public function testCanSetAndGetAttribute(): void
    {
        $request = new Request('GET', '/graphql');
        $newRequest = $request->withAttribute('user_id', 123);

        $this->assertNotSame($request, $newRequest);
        $this->assertNull($request->getAttribute('user_id'));
        $this->assertEquals(123, $newRequest->getAttribute('user_id'));
        $this->assertEquals(456, $newRequest->getAttribute('nonexistent', 456));
    }

    public function testCanCreateFromArray(): void
    {
        $data = [
            'method' => 'POST',
            'path' => '/graphql',
            'headers' => ['Content-Type' => 'application/json'],
            'body' => '{"query": "{ hello }"}',
            'parsedBody' => ['query' => '{ hello }'],
            'queryParams' => ['key' => 'value'],
        ];

        $request = Request::create($data);

        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/graphql', $request->getPath());
        $this->assertEquals('application/json', $request->getHeader('Content-Type'));
        $this->assertEquals('{"query": "{ hello }"}', $request->getBody());
        $this->assertEquals(['query' => '{ hello }'], $request->getParsedBody());
        $this->assertEquals(['key' => 'value'], $request->getQueryParams());
    }
}

