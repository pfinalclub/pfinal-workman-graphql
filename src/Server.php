<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL;

use GraphQL\Error\Error;
use GraphQL\Type\Schema as GraphQLSchema;
use PFinalClub\WorkermanGraphQL\Adapter\ServerAdapterInterface;
use PFinalClub\WorkermanGraphQL\Adapter\WorkermanAdapter;
use PFinalClub\WorkermanGraphQL\Http\JsonResponse;
use PFinalClub\WorkermanGraphQL\Http\RequestInterface;
use PFinalClub\WorkermanGraphQL\Http\Response;
use PFinalClub\WorkermanGraphQL\Http\ResponseInterface;
use PFinalClub\WorkermanGraphQL\Middleware\MiddlewareInterface;
use PFinalClub\WorkermanGraphQL\Middleware\MiddlewarePipeline;
use PFinalClub\WorkermanGraphQL\Schema\CodeSchemaBuilder;
use PFinalClub\WorkermanGraphQL\Schema\SchemaBuilderInterface;

final class Server
{
    private GraphQLEngine $engine;

    private ServerAdapterInterface $adapter;

    private SchemaBuilderInterface $schemaBuilder;

    /**
     * @var array<string, mixed>
     */
    private array $config;

    private MiddlewarePipeline $middlewarePipeline;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        array $config = [],
        ?ServerAdapterInterface $adapter = null,
        ?GraphQLEngine $engine = null,
        ?SchemaBuilderInterface $schemaBuilder = null
    ) {
        $this->config = array_merge($this->defaultConfig(), $config);
        $this->engine = $engine ?? new GraphQLEngine(null, (bool) $this->config['debug']);
        $this->adapter = $adapter ?? new WorkermanAdapter($this->config['server']);
        $this->schemaBuilder = $schemaBuilder ?? new CodeSchemaBuilder();
        $this->middlewarePipeline = new MiddlewarePipeline();

        $this->engine->setSchemaFactory(fn(): GraphQLSchema => $this->schemaBuilder->build());
    }

    public function start(): void
    {
        $this->adapter->start(fn(RequestInterface $request): ResponseInterface => $this->handleRequest($request));
    }

    public function useSchemaBuilder(SchemaBuilderInterface $builder): self
    {
        $this->schemaBuilder = $builder;
        $this->engine->setSchemaFactory(fn(): GraphQLSchema => $this->schemaBuilder->build());

        return $this;
    }

    /**
     * @param callable(SchemaBuilderInterface): (SchemaBuilderInterface|GraphQLSchema|null) $callback
     */
    public function configureSchema(callable $callback): self
    {
        $result = $callback($this->schemaBuilder);

        if ($result instanceof GraphQLSchema) {
            $this->engine->setSchema($result);
        } elseif ($result instanceof SchemaBuilderInterface) {
            $this->useSchemaBuilder($result);
        }

        return $this;
    }

    public function setSchema(GraphQLSchema $schema): self
    {
        $this->engine->setSchema($schema);

        return $this;
    }

    public function setContextFactory(callable $factory): self
    {
        $this->engine->setContextFactory($factory);

        return $this;
    }

    public function setErrorFormatter(callable $formatter): self
    {
        /** @var callable(Error, bool): array<string, mixed> $formatter */
        $this->engine->setErrorFormatter($formatter);

        return $this;
    }

    public function addMiddleware(MiddlewareInterface $middleware): self
    {
        $this->middlewarePipeline->add($middleware);

        return $this;
    }

    public function setDebug(bool $debug): self
    {
        $this->config['debug'] = $debug;
        $this->engine->setDebug($debug);

        return $this;
    }

    public function setSchemaCacheTTL(int $seconds): self
    {
        $this->engine->setSchemaCacheTTL($seconds);

        return $this;
    }

    public function clearSchemaCache(): self
    {
        $this->engine->clearSchemaCache();

        return $this;
    }

    private function handleRequest(RequestInterface $request): ResponseInterface
    {
        $endpoint = $this->config['endpoint'];

        if ($request->getPath() !== $endpoint) {
            return Response::create(404, ['Content-Type' => 'text/plain; charset=utf-8'], 'Not Found');
        }

        if ($request->getMethod() === 'GET' && $this->shouldServeGraphiQL($request)) {
            return Response::create(200, ['Content-Type' => 'text/html; charset=utf-8'], $this->graphiqlHtml());
        }

        $finalHandler = fn(RequestInterface $req): ResponseInterface => $this->engine->handle($req);

        return $this->middlewarePipeline->handle($request, $finalHandler);
    }

    private function shouldServeGraphiQL(RequestInterface $request): bool
    {
        // 生产环境检查
        $appEnv = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'development';
        if ($appEnv === 'production') {
            return false;
        }

        if (!$this->config['graphiql']) {
            return false;
        }

        $accept = $request->getHeader('accept', '');

        return str_contains((string) $accept, 'text/html');
    }

    private function graphiqlHtml(): string
    {
        // 生成 CSP nonce 以防止 XSS
        $nonce = base64_encode(random_bytes(16));
        $endpoint = htmlspecialchars($this->config['endpoint'], ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'nonce-{$nonce}' https://unpkg.com; style-src 'self' 'nonce-{$nonce}' https://unpkg.com; connect-src 'self'; img-src 'self' data:; font-src 'self' data:;" />
    <title>GraphiQL</title>
    <meta name="robots" content="noindex" />
    <meta name="referrer" content="origin" />
    <style nonce="{$nonce}">
        html, body { height: 100%; margin: 0; overflow: hidden; }
        #graphiql { height: 100vh; }
    </style>
    <link rel="stylesheet" href="https://unpkg.com/graphiql/graphiql.min.css" />
</head>
<body>
    <div id="graphiql">Loading...</div>
    <script nonce="{$nonce}" crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script nonce="{$nonce}" crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    <script nonce="{$nonce}" src="https://unpkg.com/graphiql/graphiql.min.js"></script>
    <script nonce="{$nonce}">
        const graphQLEndpoint = window.location.origin + '{$endpoint}';
        const fetcher = GraphiQL.createFetcher({ url: graphQLEndpoint });
        const root = ReactDOM.createRoot(document.getElementById('graphiql'));
        root.render(React.createElement(GraphiQL, { fetcher }));
    </script>
</body>
</html>
HTML;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultConfig(): array
    {
        return [
            'endpoint' => '/graphql',
            'graphiql' => true,
            'debug' => false,
            'server' => [
                'host' => '0.0.0.0',
                'port' => 8080,
                'name' => 'workerman-graphql',
                'worker_count' => 4,
            ],
        ];
    }
}

