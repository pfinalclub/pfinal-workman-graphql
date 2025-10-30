<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Integration\Laravel;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PFinalClub\WorkermanGraphQL\GraphQLEngine;
use PFinalClub\WorkermanGraphQL\Http\Request as GraphQLRequest;
use PFinalClub\WorkermanGraphQL\Middleware\MiddlewarePipeline;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class GraphQLController extends Controller
{
    public function __construct(
        private GraphQLEngine $engine,
        private MiddlewarePipeline $pipeline
    ) {
    }

    public function __invoke(Request $request): SymfonyResponse
    {
        $graphQLRequest = new GraphQLRequest(
            $request->getMethod(),
            $request->getPathInfo(),
            $this->normalizeHeaders($request),
            (string) $request->getContent(),
            $this->resolveParsedBody($request),
            $request->query->all(),
            []
        );

        $response = $this->pipeline->handle(
            $graphQLRequest,
            fn($req) => $this->engine->handle($req)
        );

        return response($response->getBody(), $response->getStatusCode())
            ->withHeaders($response->getHeaders());
    }

    /**
     * @return array<string, string|string[]>
     */
    private function normalizeHeaders(Request $request): array
    {
        $headers = [];

        foreach ($request->headers->all() as $name => $value) {
            $headers[$name] = count($value) === 1 ? (string) $value[0] : $value;
        }

        return $headers;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveParsedBody(Request $request): ?array
    {
        if ($request->isJson()) {
            $decoded = $request->json()->all();

            return is_array($decoded) ? $decoded : null;
        }

        $payload = $request->request->all();

        return $payload === [] ? null : $payload;
    }
}

