<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Repositories\Concerns;

use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Typdy\StarterKit\Api\Contracts\Client;
use Typdy\StarterKit\Api\Enums\HttpMethod;
use Typdy\StarterKit\Parsers\Contracts\DocumentParser as DocumentParserContract;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Parsers\Exceptions\DecodingException;
use Typdy\StarterKit\Parsers\Exceptions\ResponseParserException;
use Typdy\StarterKit\Parsers\ResponseParser;
use Typdy\StarterKit\Repositories\Data\Request;
use Typdy\StarterKit\Repositories\Exceptions\HttpNotFoundException;
use Typdy\StarterKit\Typdy;

use function count;
use function http_build_query;
use function is_array;
use function is_int;
use function is_string;
use function method_exists;
use function urlencode;

/**
 * Coordinates sending API requests for a repository.
 *
 * @api
 */
trait SendsApiRequests
{
    public protected(set) Client $client;

    protected bool $throws = true;

    /**
     * @internal
     */
    private ?bool $_originalThrows = null;

    abstract public function getBlueprint(): string;

    abstract public function getEndpoint(): string;

    abstract public function getProject(): string;

    abstract public function getTeam(): string;

    abstract public function isGlobal(): bool;

    /**
     * @return $this
     */
    public function returnsOnError(): self
    {
        $this->setOriginalThrows();

        $this->throws = false;

        return $this;
    }

    /**
     * @return $this
     */
    public function throws(): self
    {
        $this->setOriginalThrows();

        $this->throws = true;

        return $this;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    final protected function buildPath(int|string|null $id = null, array $parameters = []): string
    {
        $path = $this->getEndpoint();

        if ($id !== null) {
            $path .= '/' . urlencode((string) $id);
        }

        if (count($parameters) > 0) {
            $path .= '?' . http_build_query($parameters);
        }

        return $path;
    }

    protected function getResponseParser(): ResponseParser
    {
        $documentParser = Typdy::container(DocumentParserContract::class);

        if ($this->isGlobal()) {
            return new ResponseParser($documentParser, mixedType: true);
        }

        return new ResponseParser($documentParser, $this->getBlueprint());
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $defaults
     */
    final protected function prepareRequest(
        array $request,
        int|string|null $id = null,
        array $defaults = [],
    ): Request {
        $request['parameters'] ??= [];
        $request['headers'] ??= [];

        if (count($defaults) > 0) {
            // @mago-expect analysis:mixed-assignment
            foreach ($defaults as $key => $value) {
                if (!is_array($value)) {
                    continue;
                }

                // @mago-expect analysis:invalid-array-element
                $request[$key] = [...$value, ...($request[$key] ?? [])];
            }
        }

        $identifier = is_string($id) && $id !== '' ? $id : null;
        $id = is_int($id) ? $id : null;

        // global identifiers match their blueprint identifier
        $blueprint = $this->isGlobal() ? $identifier : $this->getBlueprint();

        $collection = null;

        if (method_exists($this, 'getCollection')) {
            /** @var string $collection */
            $collection = $this->getCollection();
        }

        return new Request(
            team: $this->getTeam(),
            project: $this->getProject(),
            collection: $collection,
            blueprint: $blueprint,
            isGlobal: $this->isGlobal(),
            query: $request,
            id: $id,
            identifier: $identifier,
        );
    }

    /**
     * @param array<string, mixed> $parameters
     * @param string|resource|StreamInterface|null $body
     * @param array<string, string> $headers
     *
     * @throws JsonException
     * @throws ClientExceptionInterface
     * @throws ResponseParserException
     * @throws DecodingException
     * @throws HttpNotFoundException
     */
    final protected function request(
        int|string|null $id = null,
        HttpMethod $method = HttpMethod::GET,
        mixed $body = null,
        array $parameters = [],
        array $headers = [],
    ): Document {
        $response = $this->client->request(
            path: $this->buildPath($id, $parameters),
            method: $method,
            body: $body,
            headers: $headers,
            useMapi: property_exists($this, 'mapi') && is_bool($this->mapi)
                ? $this->mapi
                : false,
        );

        $document = $this->getResponseParser()->parse($response);

        $throws = $this->throws;

        $this->resetThrows();

        if ($throws && $document->failed()) {
            $code = $document->response->getStatusCode();
            $message = $document->response->getReasonPhrase();

            if (method_exists($this, 'throwResponseException')) {
                $this->throwResponseException($document);
            } elseif (!$document->wasNotFound()) {
                throw new RuntimeException("Received unsuccessful response from typdy: {$code} '{$message}'.");
            }
        }

        return $document;
    }

    final protected function resetThrows(): void
    {
        if ($this->_originalThrows !== null) {
            $this->throws = $this->_originalThrows;
        }
    }

    final protected function setOriginalThrows(): void
    {
        if ($this->_originalThrows === null) {
            $this->_originalThrows = $this->throws;
        }
    }
}
