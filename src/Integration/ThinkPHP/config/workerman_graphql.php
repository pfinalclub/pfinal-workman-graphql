<?php

declare(strict_types=1);

return [
    'debug' => env('APP_DEBUG', false),

    'schema' => root_path('graphql/schema.graphql'),

    'middleware' => [
        PFinalClub\WorkermanGraphQL\Middleware\ErrorHandlerMiddleware::class,
        PFinalClub\WorkermanGraphQL\Middleware\CorsMiddleware::class,
    ],
];

