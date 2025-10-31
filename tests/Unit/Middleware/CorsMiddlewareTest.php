<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Tests\Unit\Middleware;

use PFinalClub\WorkermanGraphQL\Http\Request;
use PFinalClub\WorkermanGraphQL\Http\Response;
use PFinalClub\WorkermanGraphQL\Middleware\CorsMiddleware;
use PHPUnit\Framework\TestCase;

final class CorsMiddlewareTest extends TestCase
{
    public function testHandlesOptionsRequest(): void
    {
        $middleware = new CorsMiddleware();
        $request = new Request('OPTIONS', '/graphql');

        $response = $middleware->process($request, fn() => Response::create(200));

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertNotEmpty($response->getHeader('Access-Control-Allow-Origin'));
    }

    public function testAddsCorsHeadersToResponse(): void
    {
        $middleware = new CorsMiddleware();
        $request = new Request('POST', '/graphql', ['Origin' => 'https://example.com']);
        $nextResponse = Response::create(200);

        $response = $middleware->process($request, fn() => $nextResponse);

        $this->assertNotEmpty($response->getHeader('Access-Control-Allow-Origin'));
        $this->assertNotEmpty($response->getHeader('Access-Control-Allow-Methods'));
        $this->assertNotEmpty($response->getHeader('Access-Control-Allow-Headers'));
    }

    public function testRespectsAllowedOrigins(): void
    {
        $middleware = new CorsMiddleware([
            'allow_origin' => ['https://example.com'],
        ]);
        $request = new Request('POST', '/graphql', ['Origin' => 'https://example.com']);

        $response = $middleware->process($request, fn() => Response::create(200));

        $this->assertEquals('https://example.com', $response->getHeader('Access-Control-Allow-Origin'));
    }

    public function testHandlesWildcardOrigin(): void
    {
        $middleware = new CorsMiddleware(['allow_origin' => ['*']]);
        $request = new Request('POST', '/graphql', ['Origin' => 'https://anyorigin.com']);

        $response = $middleware->process($request, fn() => Response::create(200));

        $this->assertEquals('*', $response->getHeader('Access-Control-Allow-Origin'));
    }

    public function testAddsCredentialsHeaderWhenEnabled(): void
    {
        $middleware = new CorsMiddleware(['allow_credentials' => true]);
        $request = new Request('POST', '/graphql');

        $response = $middleware->process($request, fn() => Response::create(200));

        $this->assertEquals('true', $response->getHeader('Access-Control-Allow-Credentials'));
    }

    public function testAddsMaxAgeHeader(): void
    {
        $middleware = new CorsMiddleware(['max_age' => 3600]);
        $request = new Request('POST', '/graphql');

        $response = $middleware->process($request, fn() => Response::create(200));

        $this->assertEquals('3600', $response->getHeader('Access-Control-Max-Age'));
    }
}

