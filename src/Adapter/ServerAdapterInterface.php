<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Adapter;

use PFinalClub\WorkermanGraphQL\Http\RequestInterface;
use PFinalClub\WorkermanGraphQL\Http\ResponseInterface;

interface ServerAdapterInterface
{
    /**
     * @param callable(RequestInterface): ResponseInterface $handler
     */
    public function start(callable $handler): void;
}

