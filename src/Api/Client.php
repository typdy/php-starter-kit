<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Api;

use Override;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use PsrDiscovery\Discover;
use RuntimeException;
use Typdy\StarterKit\Api\Enums\HttpMethod;

use function is_resource;
use function is_string;
use function ltrim;
use function rtrim;

final class Client implements Contracts\Client
{
    private const string API_BASE_URL = 'https://api.tcms.io';

    private const string MAPI_BASE_URL = 'https://mapi.tcms.io';

    /**
     * @var array<string, string>
     */
    public array $defaultHeaders = [
        'Accept' => 'application/vnd.api+json',
        'Content-Type' => 'application/vnd.api+json',
    ];

    private ClientInterface $client;

    private RequestFactoryInterface $requestFactory;

    private StreamFactoryInterface $streamFactory;

    public function __construct(
        ?ClientInterface $client = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        $client ??= Discover::httpClient();
        $requestFactory ??= Discover::httpRequestFactory();
        $streamFactory ??= Discover::httpStreamFactory();

        if ($client === null || $requestFactory === null || $streamFactory === null) {
            throw new RuntimeException('Failed to discover HTTP client, request factory, or stream factory.');
        }

        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
    }

    #[Override]
    public function getApiBaseUrl(): string
    {
        return self::API_BASE_URL;
    }

    #[Override]
    public function getMapiBaseUrl(): string
    {
        return self::MAPI_BASE_URL;
    }

    /**
     * @param string|resource|StreamInterface|null $body
     * @param array<string, string> $headers
     *
     * @throws ClientExceptionInterface
     */
    #[Override]
    public function request(
        string $path,
        HttpMethod $method = HttpMethod::GET,
        mixed $body = null,
        array $headers = [],
        bool $useMapi = false,
    ): ResponseInterface {
        return $this->client->sendRequest(
            $this->buildRequest(
                $this->getEndpoint($path, $useMapi),
                $method->value,
                $body,
                [...$this->defaultHeaders, ...$headers],
            ),
        );
    }

    /**
     * @param string|resource|StreamInterface|null $body
     * @param array<string, string> $headers
     */
    private function buildRequest(string $endpoint, string $method, mixed $body, array $headers): RequestInterface
    {
        $request = $this->requestFactory->createRequest($method, $endpoint);

        if ($body !== null && $method !== HttpMethod::GET->value) {
            $stream = null;

            if (is_resource($body)) {
                $stream = $this->streamFactory->createStreamFromResource($body);
            }

            if (is_string($body)) {
                $stream = $this->streamFactory->createStream($body);
            }

            if ($body instanceof StreamInterface) {
                $stream = $body;
            }

            if ($stream !== null) {
                $request = $request->withBody($stream);
            }
        }

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    private function getEndpoint(string $path, bool $useMapi = false): string
    {
        $baseUrl = $useMapi ? $this->getMapiBaseUrl() : $this->getApiBaseUrl();

        return rtrim($baseUrl, characters: '/') . '/' . ltrim($path, characters: '/');
    }
}
