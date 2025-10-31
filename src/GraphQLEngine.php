<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL;

use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use JsonException;
use PFinalClub\WorkermanGraphQL\Exception\SchemaException;
use PFinalClub\WorkermanGraphQL\Http\JsonResponse;
use PFinalClub\WorkermanGraphQL\Http\RequestInterface;
use PFinalClub\WorkermanGraphQL\Http\Response;
use PFinalClub\WorkermanGraphQL\Http\ResponseInterface;
use Throwable;

final class GraphQLEngine
{
    private ?Schema $schema = null;

    /** @var callable|null */
    private $schemaFactory = null;

    /**
     * @var callable|null
     * @phpstan-var (callable(RequestInterface): Context)|null
     */
    private $contextFactory = null;

    /**
     * @var callable|null
     * @phpstan-var (callable(Error, bool): array<string, mixed>)|null
     */
    private $errorFormatter = null;

    private bool $debug;

    private ?\DateTimeImmutable $schemaCacheTime = null;

    private int $schemaCacheTTL = 3600;

    public function __construct(?Schema $schema = null, bool $debug = false)
    {
        if ($schema instanceof Schema) {
            $this->schema = $schema;
            $this->schemaCacheTime = new \DateTimeImmutable();
        }

        $this->debug = $debug;
    }

    public function setSchema(Schema $schema): void
    {
        $this->schema = $schema;
        $this->schemaCacheTime = new \DateTimeImmutable();
    }

    /**
     * @param callable(): Schema $factory
     */
    public function setSchemaFactory(callable $factory): void
    {
        $this->schemaFactory = $factory;
    }

    /**
     * @param callable(RequestInterface): Context $factory
     */
    public function setContextFactory(callable $factory): void
    {
        $this->contextFactory = $factory;
    }

    /**
     * @param callable(Error, bool): array<string, mixed> $formatter
     */
    public function setErrorFormatter(callable $formatter): void
    {
        $this->errorFormatter = $formatter;
    }

    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    public function setSchemaCacheTTL(int $seconds): void
    {
        $this->schemaCacheTTL = max(0, $seconds);
    }

    public function clearSchemaCache(): void
    {
        $this->schema = null;
        $this->schemaCacheTime = null;
    }

    public function handle(RequestInterface $request): ResponseInterface
    {
        try {
            $payload = $this->parseGraphQLRequest($request);

            if ($payload === null || empty($payload['query'])) {
                return JsonResponse::fromData(
                    ['errors' => [['message' => 'Invalid GraphQL request payload']]],
                    400
                );
            }

            $schema = $this->resolveSchema();
            $context = $this->createContext($request);

            $result = GraphQL::executeQuery(
                $schema,
                $payload['query'],
                null,
                $context,
                $payload['variables'] ?? null,
                $payload['operationName'] ?? null
            );

            $debugFlags = $this->debug ? DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE : 0;

            if ($this->errorFormatter !== null) {
                $result->setErrorFormatter($this->errorFormatter);
            }

            return JsonResponse::fromData($result->toArray($debugFlags));
        } catch (Throwable $exception) {
            return $this->createErrorResponse($exception);
        }
    }

    private function resolveSchema(): Schema
    {
        // 如果 schema 已设置且缓存有效，直接返回
        if ($this->schema instanceof Schema) {
            if ($this->schemaCacheTime === null || $this->isSchemaCacheValid()) {
                return $this->schema;
            }
            // 缓存失效，清除并重新构建
            $this->schema = null;
            $this->schemaCacheTime = null;
        }

        if ($this->schemaFactory !== null) {
            $schema = ($this->schemaFactory)();

            if (!$schema instanceof Schema) {
                throw new SchemaException('Schema factory must return an instance of ' . Schema::class);
            }

            $this->schema = $schema;
            $this->schemaCacheTime = new \DateTimeImmutable();

            return $schema;
        }

        throw new SchemaException('GraphQL schema is not configured.');
    }

    private function isSchemaCacheValid(): bool
    {
        if ($this->schemaCacheTime === null || $this->schemaCacheTTL <= 0) {
            return false;
        }

        $elapsed = (new \DateTimeImmutable())->getTimestamp() - $this->schemaCacheTime->getTimestamp();

        return $elapsed < $this->schemaCacheTTL;
    }

    private function createContext(RequestInterface $request): Context
    {
        if ($this->contextFactory !== null) {
            $context = ($this->contextFactory)($request);

            if (!$context instanceof Context) {
                throw new SchemaException('Context factory must return an instance of ' . Context::class);
            }

            return $context;
        }

        return new Context($request);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseGraphQLRequest(RequestInterface $request): ?array
    {
        $method = strtoupper($request->getMethod());

        if ($method === 'GET') {
            $query = $request->getQueryParams()['query'] ?? null;

            if (!is_string($query) || $query === '') {
                return null;
            }

            $variables = $request->getQueryParams()['variables'] ?? null;
            if (is_string($variables)) {
                $variables = $this->decodeJson($variables);
            }

            return [
                'query' => $query,
                'variables' => is_array($variables) ? $variables : null,
                'operationName' => $request->getQueryParams()['operationName'] ?? null,
            ];
        }

        if ($method !== 'POST') {
            return null;
        }

        $parsedBody = $request->getParsedBody();
        if ($parsedBody !== null) {
            return $this->normalizePayload($parsedBody);
        }

        $body = $request->getBody();
        if ($body === '') {
            return null;
        }

        $contentType = strtolower($request->getHeader('content-type', ''));

        if (str_contains($contentType, 'application/json')) {
            $decoded = $this->decodeJson($body);

            return $this->normalizePayload($decoded ?? []);
        }

        if (str_contains($contentType, 'application/graphql')) {
            return ['query' => $body];
        }

        // Fallback: try decode JSON regardless of header
        $decoded = $this->decodeJson($body);

        return $decoded === null ? null : $this->normalizePayload($decoded);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        if (!isset($payload['query']) || !is_string($payload['query']) || $payload['query'] === '') {
            return [];
        }

        $normalized = ['query' => $payload['query']];

        if (isset($payload['variables'])) {
            if (is_string($payload['variables'])) {
                $normalized['variables'] = $this->decodeJson($payload['variables']) ?? null;
            } elseif (is_array($payload['variables'])) {
                $normalized['variables'] = $payload['variables'];
            }
        }

        if (isset($payload['operationName']) && is_string($payload['operationName'])) {
            $normalized['operationName'] = $payload['operationName'];
        }

        return $normalized;
    }

    private function decodeJson(string $json): ?array
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (JsonException) {
            return null;
        }
    }

    private function createErrorResponse(Throwable $exception): ResponseInterface
    {
        if ($this->debug) {
            $error = [
                'errors' => [[
                    'message' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                ]],
            ];
        } else {
            $error = ['errors' => [['message' => 'Internal server error']]];
        }

        try {
            return JsonResponse::fromData($error, 500);
        } catch (JsonException) {
            return Response::create(500, ['Content-Type' => 'text/plain; charset=utf-8'], 'Internal server error');
        }
    }
}

