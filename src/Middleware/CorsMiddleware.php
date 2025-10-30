<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Middleware;

use PFinalClub\WorkermanGraphQL\Http\RequestInterface;
use PFinalClub\WorkermanGraphQL\Http\Response;
use PFinalClub\WorkermanGraphQL\Http\ResponseInterface;

final class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $options;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->defaultOptions(), $options);
    }

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $headers = $this->prepareHeaders($request);

        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return Response::create(204, $headers, '');
        }

        $response = $next($request);

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function prepareHeaders(RequestInterface $request): array
    {
        $origin = $request->getHeader('origin');
        $allowedOrigin = $this->resolveOrigin($origin);

        $headers = [
            'Access-Control-Allow-Origin' => $allowedOrigin,
            'Access-Control-Allow-Methods' => implode(', ', (array) $this->options['allow_methods']),
            'Access-Control-Allow-Headers' => implode(', ', (array) $this->options['allow_headers']),
        ];

        if (!empty($this->options['expose_headers'])) {
            $headers['Access-Control-Expose-Headers'] = implode(', ', (array) $this->options['expose_headers']);
        }

        if (!empty($this->options['allow_credentials'])) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        if (!empty($this->options['max_age'])) {
            $headers['Access-Control-Max-Age'] = (string) $this->options['max_age'];
        }

        return $headers;
    }

    private function resolveOrigin(?string $origin): string
    {
        $allowedOrigins = (array) $this->options['allow_origin'];

        if (in_array('*', $allowedOrigins, true)) {
            return '*';
        }

        if ($origin !== null && in_array($origin, $allowedOrigins, true)) {
            return $origin;
        }

        return $allowedOrigins[0] ?? '*';
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultOptions(): array
    {
        return [
            'allow_origin' => ['*'],
            'allow_methods' => ['GET', 'POST', 'OPTIONS'],
            'allow_headers' => ['Content-Type', 'Authorization'],
            'expose_headers' => [],
            'allow_credentials' => false,
            'max_age' => 86400,
        ];
    }
}

