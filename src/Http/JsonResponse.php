<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Http;

use JsonException;

final class JsonResponse extends Response
{
    /**
     * @param array<string, string|string[]> $headers
     * @throws JsonException
     */
    public function __construct(
        array $data,
        int $statusCode = 200,
        array $headers = [],
        int $encodingOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) {
        $headers = array_merge([
            'Content-Type' => 'application/json; charset=utf-8',
        ], $headers);

        $body = json_encode($data, $encodingOptions | JSON_THROW_ON_ERROR);

        parent::__construct($statusCode, $headers, $body === false ? '' : $body);
    }

    /**
     * @param array<string, string|string[]> $headers
     * @throws JsonException
     */
    public static function create(
        array $data,
        int $statusCode = 200,
        array $headers = [],
        int $encodingOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ): self {
        return new self($data, $statusCode, $headers, $encodingOptions);
    }
}

