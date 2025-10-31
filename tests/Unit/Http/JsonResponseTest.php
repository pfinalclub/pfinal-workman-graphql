<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Tests\Unit\Http;

use JsonException;
use PFinalClub\WorkermanGraphQL\Http\JsonResponse;
use PHPUnit\Framework\TestCase;

final class JsonResponseTest extends TestCase
{
    public function testCanCreateJsonResponse(): void
    {
        $data = ['message' => 'success'];
        $response = JsonResponse::fromData($data);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeader('Content-Type'));
        $this->assertJson($response->getBody());
        $this->assertEquals($data, json_decode($response->getBody(), true));
    }

    public function testCanCreateJsonResponseWithCustomStatus(): void
    {
        $response = JsonResponse::fromData(['error' => 'Not Found'], 404);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testCanCreateJsonResponseWithCustomHeaders(): void
    {
        $response = JsonResponse::fromData(['data' => 'test'], 200, ['X-Custom' => 'value']);

        $this->assertEquals('value', $response->getHeader('X-Custom'));
        $this->assertStringContainsString('application/json', $response->getHeader('Content-Type'));
    }

    public function testJsonResponseIncludesUtf8Encoding(): void
    {
        $data = ['{#}中文' => '测试'];
        $response = JsonResponse::fromData($data);

        $body = $response->getBody();
        $this->assertStringContainsString('中文', $body);
        $this->assertStringContainsString('测试', $body);
    }

    public function testJsonResponseHandlesUnescapedSlashes(): void
    {
        $data = ['path' => 'https://example.com/path'];
        $response = JsonResponse::fromData($data);

        $body = $response->getBody();
        $this->assertStringContainsString('https://example.com/path', $body);
        $decoded = json_decode($body, true);
        $this->assertEquals($data, $decoded);
    }
}

