<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Api;

use JsonException;
use LogicException;
use Psr\Http\Client\ClientExceptionInterface;
use Typdy\StarterKit\Api\Contracts\Client;
use Typdy\StarterKit\Api\Enums\HttpMethod;
use Typdy\StarterKit\Containers\Contracts\Container;
use Typdy\StarterKit\Parsers\Contracts\ResponseParser;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Parsers\Exceptions\ResponseParserException;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\TypdyConfig;

use function count;
use function http_build_query;
use function json_encode;
use function ltrim;

use const JSON_THROW_ON_ERROR;

final class RequestCoordinator
{
    private readonly Container $container;

    private readonly TypdyConfig $config;

    public ?string $team = null {
        get {
            return $this->team ?? $this->config->team;
        }
    }

    public ?string $project = null {
        get {
            return $this->project ?? $this->config->project;
        }
    }

    public function __construct(
        ?string $team = null,
        ?string $project = null,
        ?Container $container = null,
        ?TypdyConfig $config = null,
    ) {
        $this->team = $team;
        $this->project = $project;
        $this->container = $container ?? Typdy::container();
        $this->config = $config ?? Typdy::$config;
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, string> $headers
     *
     * @throws JsonException|ClientExceptionInterface
     * @throws ResponseParserException
     */
    public function request(
        string $path,
        HttpMethod $method = HttpMethod::GET,
        mixed $body = null,
        array $parameters = [],
        array $headers = [],
        bool $useMapi = false,
    ): Document {
        $client = $this->container->make(Client::class);

        $requestBody = null;

        if ($body !== null && $method !== HttpMethod::GET) {
            $requestBody = json_encode($body, JSON_THROW_ON_ERROR);
        }

        $response = $client->request(
            path: $this->buildPath($path, $parameters),
            method: $method,
            body: $requestBody,
            headers: $headers,
            useMapi: $useMapi,
        );

        return $this->container
            ->make(ResponseParser::class, ['mixedType' => true])
            ->parse($response);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function buildPath(string $path, array $parameters = []): string
    {
        if ($this->team === null || $this->project === null) {
            throw new LogicException('Team and project must be configured before making a request.');
        }

        $endpoint = '@' . $this->team . '/' . $this->project . '/';

        $path = $endpoint . ltrim($path, characters: '/');

        if (count($parameters) > 0) {
            $path .= '?' . http_build_query($parameters);
        }

        return $path;
    }
}
