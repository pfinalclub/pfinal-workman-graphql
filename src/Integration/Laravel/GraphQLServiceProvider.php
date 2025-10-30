<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Integration\Laravel;

use GraphQL\Type\Schema as GraphQLSchema;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use PFinalClub\WorkermanGraphQL\GraphQLEngine;
use PFinalClub\WorkermanGraphQL\Middleware\MiddlewareInterface;
use PFinalClub\WorkermanGraphQL\Middleware\MiddlewarePipeline;
use PFinalClub\WorkermanGraphQL\Schema\CodeSchemaBuilder;
use PFinalClub\WorkermanGraphQL\Schema\SchemaBuilderInterface;
use PFinalClub\WorkermanGraphQL\Schema\SdlSchemaBuilder;
use RuntimeException;

final class GraphQLServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/workerman-graphql.php', 'workerman-graphql');

        $this->app->singleton(GraphQLEngine::class, function ($app) {
            $config = (array) $app['config']->get('workerman-graphql', []);

            $engine = new GraphQLEngine(null, (bool) ($config['debug'] ?? false));

            $schemaSource = $this->resolveSchemaSource($config, $app);

            if ($schemaSource instanceof GraphQLSchema) {
                $engine->setSchema($schemaSource);
            } elseif ($schemaSource instanceof SchemaBuilderInterface) {
                $engine->setSchemaFactory(fn(): GraphQLSchema => $schemaSource->build());
            } else {
                throw new RuntimeException('Invalid schema configuration for Workerman GraphQL.');
            }

            return $engine;
        });

        $this->app->singleton(MiddlewarePipeline::class, function ($app) {
            $pipeline = new MiddlewarePipeline();
            $config = (array) $app['config']->get('workerman-graphql', []);

            foreach ((array) ($config['middleware'] ?? []) as $middleware) {
                $instance = $this->resolveMiddleware($middleware, $app);

                if ($instance instanceof MiddlewareInterface) {
                    $pipeline->add($instance);
                }
            }

            return $pipeline;
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/workerman-graphql.php' => 
                    $this->app->configPath('workerman-graphql.php'),
            ], 'workerman-graphql-config');
        }

        Route::macro('workermanGraphQL', function (string $uri = 'graphql') {
            return Route::match(['get', 'post'], $uri, GraphQLController::class)
                ->name('workerman-graphql');
        });
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resolveSchemaSource(array $config, $app): SchemaBuilderInterface|GraphQLSchema
    {
        $schema = $config['schema'] ?? null;

        if ($schema instanceof GraphQLSchema || $schema instanceof SchemaBuilderInterface) {
            return $schema;
        }

        if (is_string($schema) && class_exists($schema)) {
            $instance = $app->make($schema);

            if ($instance instanceof GraphQLSchema || $instance instanceof SchemaBuilderInterface) {
                return $instance;
            }
        }

        if (is_callable($schema)) {
            $instance = $schema($app);

            if ($instance instanceof GraphQLSchema || $instance instanceof SchemaBuilderInterface) {
                return $instance;
            }
        }

        $schemaPath = is_string($schema) ? $schema : base_path('graphql/schema.graphql');

        if (is_string($schemaPath) && is_file($schemaPath)) {
            return (new SdlSchemaBuilder())->fromFile($schemaPath);
        }

        $builder = new CodeSchemaBuilder();

        $builder->addQuery('hello', [
            'type' => \GraphQL\Type\Definition\Type::string(),
            'resolve' => static fn(): string => 'Hello from Workerman GraphQL',
        ]);

        return $builder;
    }

    private function resolveMiddleware(mixed $middleware, $app): ?MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        if (is_string($middleware) && class_exists($middleware)) {
            return $app->make($middleware);
        }

        if (is_callable($middleware)) {
            $instance = $middleware($app);

            if ($instance instanceof MiddlewareInterface) {
                return $instance;
            }
        }

        return null;
    }
}

