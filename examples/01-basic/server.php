<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use GraphQL\Type\Definition\Type;
use PFinalClub\WorkermanGraphQL\Middleware\CorsMiddleware;
use PFinalClub\WorkermanGraphQL\Middleware\ErrorHandlerMiddleware;
use PFinalClub\WorkermanGraphQL\Schema\CodeSchemaBuilder;
use PFinalClub\WorkermanGraphQL\Server;

$server = new Server([
    'server' => [
        'host' => '127.0.0.1',
        'port' => 8080,
        'name' => 'basic-graphql-server',
        'worker_count' => 1,
    ],
    'debug' => true,
]);

$server
    ->addMiddleware(new ErrorHandlerMiddleware(true))
    ->addMiddleware(new CorsMiddleware());

$server->configureSchema(function (CodeSchemaBuilder $builder): void {
    $builder->addQuery('hello', [
        'type' => Type::nonNull(Type::string()),
        'args' => [
            'name' => [
                'type' => Type::string(),
                'description' => 'Name to greet',
            ],
        ],
        'resolve' => static fn($rootValue, array $args): string => 'Hello ' . ($args['name'] ?? 'World'),
        'description' => 'A simple greeting field',
    ]);
});

$server->start();

