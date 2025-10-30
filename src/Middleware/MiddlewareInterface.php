<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Middleware;

use PFinalClub\WorkermanGraphQL\Http\RequestInterface;
use PFinalClub\WorkermanGraphQL\Http\ResponseInterface;

interface MiddlewareInterface
{
    /**
     * @param callable(RequestInterface): ResponseInterface $next
     */
    public function process(RequestInterface $request, callable $next): ResponseInterface;
}

