<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Api\Contracts;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Typdy\StarterKit\Api\Enums\HttpMethod;

/**
 * @internal
 */
interface Client
{
    public function getApiBaseUrl(): string;

    public function getMapiBaseUrl(): string;

    /**
     * @param string|resource|StreamInterface|null $body
     * @param array<string, string> $headers
     *
     * @throws ClientExceptionInterface
     */
    public function request(
        string $path,
        HttpMethod $method = HttpMethod::GET,
        mixed $body = null,
        array $headers = [],
        bool $useMapi = false,
    ): ResponseInterface;
}
