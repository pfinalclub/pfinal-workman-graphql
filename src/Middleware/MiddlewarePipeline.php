<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Middleware;

use PFinalClub\WorkermanGraphQL\Http\RequestInterface;
use PFinalClub\WorkermanGraphQL\Http\ResponseInterface;

final class MiddlewarePipeline
{
    /**
     * @var MiddlewareInterface[]
     */
    private array $middleware = [];

    public function add(MiddlewareInterface $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * @param callable(RequestInterface): ResponseInterface $finalHandler
     */
    public function handle(RequestInterface $request, callable $finalHandler): ResponseInterface
    {
        $handler = array_reduce(
            array_reverse($this->middleware),
            static function (callable $next, MiddlewareInterface $middleware): callable {
                return static function (RequestInterface $request) use ($middleware, $next): ResponseInterface {
                    return $middleware->process($request, $next);
                };
            },
            $finalHandler
        );

        return $handler($request);
    }
}

