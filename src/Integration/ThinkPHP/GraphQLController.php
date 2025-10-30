<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Integration\ThinkPHP;

use PFinalClub\WorkermanGraphQL\GraphQLEngine;
use PFinalClub\WorkermanGraphQL\Http\Request as GraphQLRequest;
use PFinalClub\WorkermanGraphQL\Middleware\MiddlewarePipeline;
use think\Request;
use think\Response;

final class GraphQLController
{
    public function __construct(
        private GraphQLEngine $engine,
        private MiddlewarePipeline $pipeline
    ) {
    }

    public function handle(Request $request): Response
    {
        $graphQLRequest = new GraphQLRequest(
            $request->method(),
            '/' . ltrim($request->pathinfo(), '/'),
            $request->header(),
            (string) $request->getContent(),
            $this->resolveParsedBody($request),
            $request->get(),
            []
        );

        $response = $this->pipeline->handle(
            $graphQLRequest,
            fn($req) => $this->engine->handle($req)
        );

        return Response::create($response->getBody(), 'json', $response->getStatusCode())
            ->header($response->getHeaders());
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveParsedBody(Request $request): ?array
    {
        if ($request->isJson()) {
            $decoded = json_decode((string) $request->getContent(), true);

            return is_array($decoded) ? $decoded : null;
        }

        $post = $request->post();

        return $post === [] ? null : $post;
    }
}

