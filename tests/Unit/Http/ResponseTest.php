<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Tests\Unit\Http;

use PFinalClub\WorkermanGraphQL\Http\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testCanCreateResponse(): void
    {
        $response = Response::create();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertEquals('', $response->getBody());
    }

    public function testCanCreateResponseWithParameters(): void
    {
        $headers = ['Content-Type' => 'application/json'];
        $body = '{"data": {"hello": "world"}}';

        $response = Response::create(201, $headers, $body);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals($headers, $response->getHeaders());
        $this->assertEquals($body, $response->getBody());
    }

    public function testCanUpdateStatus(): void
    {
        $response = Response::create(200);
        $newResponse = $response->withStatus(404);

        $this->assertNotSame($response, $newResponse);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(404, $newResponse->getStatusCode());
    }

    public function testCanUpdateHeader(): void
    {
        $response = Response::create();
        $newResponse = $response->withHeader('Content-Type', 'application/json');

        $this->assertNotSame($response, $newResponse);
        $this->assertEquals([], $response->getHeaders());
        $this->assertEquals(['Content-Type' => 'application/json'], $newResponse->getHeaders());
    }

    public function testCanUpdateMultipleHeaders(): void
    {
        $response = Response::create();
        $headers = [
            'Content-Type' => 'application/json',
            'X-Custom' => 'value',
        ];
        $newResponse = $response->withHeaders($headers);

        $this->assertNotSame($response, $newResponse);
        $this->assertEquals($headers, $newResponse->getHeaders());
    }

    public function testCanUpdateBody(): void
    {
        $response = Response::create();
        $newResponse = $response->withBody('new body');

        $this->assertNotSame($response, $newResponse);
        $this->assertEquals('', $response->getBody());
        $this->assertEquals('new body', $newResponse->getBody());
    }
}

