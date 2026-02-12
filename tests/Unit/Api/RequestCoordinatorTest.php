<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Typdy\StarterKit\Api\Contracts\Client;
use Typdy\StarterKit\Api\Enums\HttpMethod;
use Typdy\StarterKit\Api\RequestCoordinator;
use Typdy\StarterKit\Containers\Contracts\Container;
use Typdy\StarterKit\Parsers\Contracts\ResponseParser;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\TypdyConfig;

beforeEach(function () {
    $this->mockResponse = mock(ResponseInterface::class);
    $this->clientMock = mock(Client::class);
    $this->parserMock = mock(ResponseParser::class);
    $this->containerMock = mock(Container::class);

    $this->containerMock
        ->shouldReceive('make')
        ->with(Client::class)
        ->once()
        ->andReturn($this->clientMock);

    $this->containerMock
        ->shouldReceive('make')
        ->with(ResponseParser::class, ['mixedType' => true])
        ->zeroOrMoreTimes()
        ->andReturn($this->parserMock);
});

it('sends a POST request and returns a Document', function () {
    $this->clientMock
        ->shouldReceive('request')
        ->once()
        ->withArgs(function ($path, $method, $body, $headers, $useMapi) {
            expect($path)->toBe('@test-team/test-project/pages?' . urlencode('filter[published]') . '=true');
            expect($method)->toBe(HttpMethod::POST);
            expect($body)->toBe(json_encode(['data' => 1]));
            expect($headers)->toBe(['X-Test' => '1']);
            expect($useMapi)->toBeTrue();

            return true;
        })
        ->andReturn($this->mockResponse);

    $config = new TypdyConfig(
        team: 'config-team',
        project: 'config-project',
    );

    $document = new Document($this->mockResponse);

    $this->parserMock
        ->shouldReceive('parse')
        ->once()
        ->with($this->mockResponse)
        ->andReturn($document);

    $result = new RequestCoordinator(
        team: 'test-team',
        project: 'test-project',
        container: $this->containerMock,
        config: $config,
    )
        ->request(
            path: '/pages',
            method: HttpMethod::POST,
            body: ['data' => 1],
            parameters: ['filter[published]' => 'true'],
            headers: ['X-Test' => '1'],
            useMapi: true,
        );

    expect($result)->toBeInstanceOf(Document::class);
});

it('falls back to config team and project when not provided', function () {
    $this->clientMock
        ->shouldReceive('request')
        ->once()
        ->withArgs(function ($path) {
            expect($path)->toBe('@config-team/config-project/pages');

            return true;
        })
        ->andReturn($this->mockResponse);

    $config = new TypdyConfig(
        team: 'config-team',
        project: 'config-project',
    );

    $document = new Document($this->mockResponse);

    $this->parserMock
        ->shouldReceive('parse')
        ->once()
        ->with($this->mockResponse)
        ->andReturn($document);

    $result = new RequestCoordinator(
        team: null,
        project: null,
        container: $this->containerMock,
        config: $config,
    )
        ->request(
            path: '/pages',
            method: HttpMethod::GET,
        );

    expect($result)->toBeInstanceOf(Document::class);
});

it('throws JsonException when request body cannot be JSON encoded', function () {
    $recursive = [];
    $recursive['self'] = &$recursive;

    $config = new TypdyConfig(
        team: 'test-team',
        project: 'test-project',
    );

    expect(fn () => new RequestCoordinator(
        team: null,
        project: null,
        container: $this->containerMock,
        config: $config,
    )
        ->request(
            path: '/pages',
            method: HttpMethod::POST,
            body: $recursive,
        ))
        ->toThrow(JsonException::class);
});
