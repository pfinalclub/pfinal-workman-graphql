<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Middleware;

use DateTimeImmutable;
use PFinalClub\WorkermanGraphQL\Http\RequestInterface;
use PFinalClub\WorkermanGraphQL\Http\ResponseInterface;
use Psr\Log\LoggerInterface;

final class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $start = microtime(true);
        $response = $next($request);
        $duration = microtime(true) - $start;

        $this->logger->info('GraphQL request handled', [
            'method' => $request->getMethod(),
            'path' => $request->getPath(),
            'status' => $response->getStatusCode(),
            'duration_ms' => (int) round($duration * 1000),
            'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return $response;
    }
}

