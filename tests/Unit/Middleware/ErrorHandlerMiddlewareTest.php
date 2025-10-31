<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Tests\Unit\Middleware;

use Exception;
use PFinalClub\WorkermanGraphQL\Http\JsonResponse;
use PFinalClub\WorkermanGraphQL\Http\Request;
use PFinalClub\WorkermanGraphQL\Middleware\ErrorHandlerMiddleware;
use PHPUnit\Framework\TestCase;

final class ErrorHandlerMiddlewareTest extends TestCase
{
    public function testPassesThroughSuccessfulRequest(): void
    {
        $middleware = new ErrorHandlerMiddleware();
        $request = new Request('POST', '/graphql');
        $expectedResponse = JsonResponse::fromData(['data' => 'success']);

        $response = $middleware->process($request, fn() => $expectedResponse);

        $this->assertSame($expectedResponse, $response);
    }

    public function testCatchesExceptionAndReturnsErrorResponse(): void
    {
        $middleware = new ErrorHandlerMiddleware(false);
        $request = new Request('POST', '/graphql');
        $exception = new Exception('Test error');

        $response = $middleware->process($request, function () use ($exception) {
            throw $exception;
        });

        $this->assertEquals(500, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('errors', $body);
        $this->assertEquals('Internal server error', $body['errors'][0]['message']);
    }

    public function testIncludesErrorDetailsInDebugMode(): void
    {
        $middleware = new ErrorHandlerMiddleware(true);
        $request = new Request('POST', '/graphql');
        $exception = new Exception('Test error message');

        $response = $middleware->process($request, function () use ($exception) {
            throw $exception;
        });

        $this->assertEquals(500, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('errors', $body);
        $this->assertEquals('Test error message', $body['errors'][0]['message']);
        $this->assertArrayHasKey('trace', $body['errors'][0]);
    }
}

