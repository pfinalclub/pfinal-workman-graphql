<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use PFinalClub\WorkermanGraphQL\Middleware\ErrorHandlerMiddleware;
use PFinalClub\WorkermanGraphQL\Schema\SdlSchemaBuilder;
use PFinalClub\WorkermanGraphQL\Server;

$server = new Server([
    'server' => [
        'host' => '127.0.0.1',
        'port' => 8081,
        'name' => 'sdl-graphql-server',
        'worker_count' => 1,
    ],
]);

$server->addMiddleware(new ErrorHandlerMiddleware(true));

$builder = (new SdlSchemaBuilder())
    ->fromFile(__DIR__ . '/schema.graphql')
    ->setResolver('Query', 'ping', static fn(): string => 'pong via SDL');

$server->useSchemaBuilder($builder);

$server->start();

