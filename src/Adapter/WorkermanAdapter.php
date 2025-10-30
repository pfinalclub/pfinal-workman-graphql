<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Adapter;

use JsonException;
use PFinalClub\WorkermanGraphQL\Http\Request;
use PFinalClub\WorkermanGraphQL\Http\RequestInterface;
use PFinalClub\WorkermanGraphQL\Http\ResponseInterface;
use Throwable;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request as WorkermanRequest;
use Workerman\Protocols\Http\Response as WorkermanResponse;
use Workerman\Worker;

final class WorkermanAdapter implements ServerAdapterInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $config;

    private ?Worker $worker = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->defaultConfig(), $config);
    }

    public function start(callable $handler): void
    {
        $socket = sprintf('http://%s:%d', $this->config['host'], $this->config['port']);

        $this->worker = new Worker($socket, $this->config['context'] ?? []);
        $this->worker->name = $this->config['name'];
        $this->worker->count = (int) $this->config['worker_count'];

        if (isset($this->config['ssl'])) {
            $this->worker->transport = 'ssl';
        }

        if (isset($this->config['reuse_port'])) {
            $this->worker->reusePort = (bool) $this->config['reuse_port'];
        }

        if (isset($this->config['on_worker_start']) && is_callable($this->config['on_worker_start'])) {
            $this->worker->onWorkerStart = $this->config['on_worker_start'];
        }

        $this->worker->onMessage = function (TcpConnection $connection, WorkermanRequest $request) use ($handler): void {
            try {
                $psrRequest = $this->transformRequest($request);
                $response = $handler($psrRequest);
                $connection->send($this->transformResponse($response));
            } catch (Throwable $exception) {
                $connection->send($this->createErrorResponse($exception));
            }
        };

        Worker::runAll();
    }

    private function transformRequest(WorkermanRequest $request): RequestInterface
    {
        $headers = $request->header();
        $rawBody = $request->rawBody() ?? '';
        $parsedBody = $request->post() ?: null;

        $contentType = strtolower($request->header('content-type') ?? '');

        if ($parsedBody === null && $rawBody !== '' && str_contains($contentType, 'application/json')) {
            $parsedBody = $this->decodeJson($rawBody);
        }

        return new Request(
            $request->method() ?? 'GET',
            $request->path() ?? '/',
            is_array($headers) ? $headers : [],
            $rawBody,
            is_array($parsedBody) ? $parsedBody : null,
            $request->get() ?: [],
            []
        );
    }

    private function transformResponse(ResponseInterface $response): WorkermanResponse
    {
        return new WorkermanResponse(
            $response->getStatusCode(),
            $response->getHeaders(),
            $response->getBody()
        );
    }

    private function createErrorResponse(Throwable $exception): WorkermanResponse
    {
        $body = json_encode([
            'errors' => [[
                'message' => 'Internal server error',
            ]],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new WorkermanResponse(500, ['Content-Type' => 'application/json; charset=utf-8'], $body ?: '');
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

    /**
     * @return array<string, mixed>
     */
    private function defaultConfig(): array
    {
        return [
            'host' => '0.0.0.0',
            'port' => 8080,
            'name' => 'workerman-graphql',
            'worker_count' => 4,
        ];
    }
}

