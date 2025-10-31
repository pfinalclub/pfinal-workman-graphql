<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Tests\Unit\Middleware;

use PFinalClub\WorkermanGraphQL\Http\Request;
use PFinalClub\WorkermanGraphQL\Http\Response;
use PFinalClub\WorkermanGraphQL\Middleware\MiddlewareInterface;
use PFinalClub\WorkermanGraphQL\Middleware\MiddlewarePipeline;
use PHPUnit\Framework\TestCase;

final class MiddlewarePipelineTest extends TestCase
{
    public function testExecutesFinalHandlerWhenNoMiddleware(): void
    {
        $pipeline = new MiddlewarePipeline();
        $request = new Request('POST', '/graphql');
        $expectedResponse = Response::create(200);

        $response = $pipeline->handle($request, fn() => $expectedResponse);

        $this->assertSame($expectedResponse, $response);
    }

    public function testExecutesMiddlewareInCorrectOrder(): void
    {
        $pipeline = new MiddlewarePipeline();
        $executionOrder = [];

        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware1->method('process')
            ->willReturnCallback(function ($request, $next) use (&$executionOrder) {
                $executionOrder[] = 'middleware1-before';
                $response = $next($request);
                $executionOrder[] = 'middleware1-after';

                return $response;
            });

        $middleware2 = $this->createMock(MiddlewareInterface::class);
        $middleware2->method('process')
            ->willReturnCallback(function ($request, $next) use (&$executionOrder) {
                $executionOrder[] = 'middleware2-before';
                $response = $next($request);
                $executionOrder[] = 'middleware2-after';

                return $response;
            });

        $pipeline->add($middleware1);
        $pipeline->add($middleware2);

        $request = new Request('POST', '/graphql');
        $pipeline->handle($request, function () use (&$executionOrder) {
            $executionOrder[] = 'handler';

            return Response::create(200);
        });

        $this->assertEquals([
            'middleware1-before',
            'middleware2-before',
            'handler',
            'middleware2-after',
            'middleware1-after',
        ], $executionOrder);
    }

    public function testMiddlewareCanModifyResponse(): void
    {
        $pipeline = new MiddlewarePipeline();

        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->method('process')
            ->willReturnCallback(function ($request, $next) {
                $response = $next($request);

                return $response->withHeader('X-Custom', 'modified');
            });

        $pipeline->add($middleware);

        $request = new Request('POST', '/graphql');
        $response = $pipeline->handle($request, fn() => Response::create(200));

        $this->assertEquals('modified', $response->getHeader('X-Custom'));
    }
}

