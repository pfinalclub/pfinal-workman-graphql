<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Middleware;

use PFinalClub\WorkermanGraphQL\Http\JsonResponse;
use PFinalClub\WorkermanGraphQL\Http\RequestInterface;
use PFinalClub\WorkermanGraphQL\Http\ResponseInterface;
use Throwable;

final class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(private bool $debug = false)
    {
    }

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        try {
            return $next($request);
        } catch (Throwable $exception) {
            $payload = [
                'errors' => [[
                    'message' => $this->debug ? $exception->getMessage() : 'Internal server error',
                ]],
            ];

            if ($this->debug) {
                $payload['errors'][0]['trace'] = $exception->getTraceAsString();
            }

            return JsonResponse::create($payload, 500);
        }
    }
}

