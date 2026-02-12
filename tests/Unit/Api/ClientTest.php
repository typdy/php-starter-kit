<?php

declare(strict_types=1);

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Typdy\StarterKit\Api\Client;
use Typdy\StarterKit\Api\Enums\HttpMethod;

it('returns API and MAPI base URLs', function () {
    $client = new Client();

    expect($client->getApiBaseUrl())->toBe('https://api.tcms.io');
    expect($client->getMapiBaseUrl())->toBe('https://mapi.tcms.io');
});

it('sends a request', function () {
    $mockClient = mock(ClientInterface::class);
    $mockRequestFactory = mock(RequestFactoryInterface::class);
    $mockStreamFactory = mock(StreamFactoryInterface::class);
    $mockRequest = mock(RequestInterface::class);
    $mockResponse = mock(ResponseInterface::class);
    $mockStream = mock(StreamInterface::class);

    $endpoint = 'https://api.tcms.io/test';
    $body = 'payload';
    $headers = ['X-Test' => 'yes'];

    $mockRequestFactory
        ->shouldReceive('createRequest')
        ->once()
        ->with('POST', $endpoint)
        ->andReturn($mockRequest);

    $mockStreamFactory
        ->shouldReceive('createStream')
        ->once()
        ->with($body)
        ->andReturn($mockStream);

    $mockRequest
        ->shouldReceive('withBody')
        ->once()
        ->with($mockStream)
        ->andReturnSelf();

    $mockRequest
        ->shouldReceive('withHeader')
        ->once()
        ->with('Accept', 'application/vnd.api+json')
        ->andReturnSelf();

    $mockRequest
        ->shouldReceive('withHeader')
        ->once()
        ->with('Content-Type', 'application/vnd.api+json')
        ->andReturnSelf();

    $mockRequest
        ->shouldReceive('withHeader')
        ->once()
        ->with('X-Test', 'yes')
        ->andReturnSelf();

    $mockClient
        ->shouldReceive('sendRequest')
        ->once()
        ->with($mockRequest)
        ->andReturn($mockResponse);

    $client = new Client($mockClient, $mockRequestFactory, $mockStreamFactory);

    $response = $client->request(
        path: '/test',
        method: HttpMethod::POST,
        body: $body,
        headers: $headers,
    );

    expect($response)->toBe($mockResponse);
});

it('uses MAPI base URL when useMapi is true', function () {
    $mockClient = mock(ClientInterface::class);
    $mockRequestFactory = mock(RequestFactoryInterface::class);
    $mockStreamFactory = mock(StreamFactoryInterface::class);
    $mockRequest = mock(RequestInterface::class);
    $mockResponse = mock(ResponseInterface::class);

    $endpoint = 'https://mapi.tcms.io/@team/project/blueprints';

    $mockRequestFactory
        ->shouldReceive('createRequest')
        ->once()
        ->with('GET', $endpoint)
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('withHeader')
        ->once()
        ->with('Accept', 'application/vnd.api+json')
        ->andReturnSelf();

    $mockRequest
        ->shouldReceive('withHeader')
        ->once()
        ->with('Content-Type', 'application/vnd.api+json')
        ->andReturnSelf();

    $mockClient
        ->shouldReceive('sendRequest')
        ->once()
        ->with($mockRequest)
        ->andReturn($mockResponse);

    $client = new Client($mockClient, $mockRequestFactory, $mockStreamFactory);

    $response = $client->request(
        path: '/@team/project/blueprints',
        method: HttpMethod::GET,
        body: null,
        headers: [],
        useMapi: true,
    );

    expect($response)->toBe($mockResponse);
});

it('allows overriding default headers', function () {
    $mockClient = mock(ClientInterface::class);
    $mockRequestFactory = mock(RequestFactoryInterface::class);
    $mockStreamFactory = mock(StreamFactoryInterface::class);
    $mockRequest = mock(RequestInterface::class);
    $mockResponse = mock(ResponseInterface::class);

    $endpoint = 'https://api.tcms.io/@team/project/pages';

    $mockRequestFactory
        ->shouldReceive('createRequest')
        ->once()
        ->with('GET', $endpoint)
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('withHeader')
        ->once()
        ->with('Accept', 'application/vnd.api+json; ext=test')
        ->andReturnSelf();

    $mockRequest
        ->shouldReceive('withHeader')
        ->once()
        ->with('Content-Type', 'application/vnd.api+json; ext=test')
        ->andReturnSelf();

    $mockClient
        ->shouldReceive('sendRequest')
        ->once()
        ->with($mockRequest)
        ->andReturn($mockResponse);

    $client = new Client($mockClient, $mockRequestFactory, $mockStreamFactory);

    $response = $client->request(
        path: '/@team/project/pages',
        method: HttpMethod::GET,
        body: null,
        headers: [
            'Accept' => 'application/vnd.api+json; ext=test',
            'Content-Type' => 'application/vnd.api+json; ext=test',
        ],
    );

    expect($response)->toBe($mockResponse);
});

it('does not attach a request body when body is null', function () {
    $mockClient = mock(ClientInterface::class);
    $mockRequestFactory = mock(RequestFactoryInterface::class);
    $mockStreamFactory = mock(StreamFactoryInterface::class);
    $mockRequest = mock(RequestInterface::class);
    $mockResponse = mock(ResponseInterface::class);

    $endpoint = 'https://api.tcms.io/@team/project/pages';

    $mockRequestFactory
        ->shouldReceive('createRequest')
        ->once()
        ->with('GET', $endpoint)
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('withBody')
        ->never();

    $mockStreamFactory
        ->shouldReceive('createStream')
        ->never();

    $mockRequest
        ->shouldReceive('withHeader')
        ->once()
        ->with('Accept', 'application/vnd.api+json')
        ->andReturnSelf();

    $mockRequest
        ->shouldReceive('withHeader')
        ->once()
        ->with('Content-Type', 'application/vnd.api+json')
        ->andReturnSelf();

    $mockClient
        ->shouldReceive('sendRequest')
        ->once()
        ->with($mockRequest)
        ->andReturn($mockResponse);

    $client = new Client($mockClient, $mockRequestFactory, $mockStreamFactory);

    $response = $client->request(
        path: '/@team/project/pages',
        method: HttpMethod::GET,
    );

    expect($response)->toBe($mockResponse);
});

it('does not attach a request body for GET when payload is provided', function () {
    $mockClient = mock(ClientInterface::class);
    $mockRequestFactory = mock(RequestFactoryInterface::class);
    $mockStreamFactory = mock(StreamFactoryInterface::class);
    $mockRequest = mock(RequestInterface::class);
    $mockResponse = mock(ResponseInterface::class);

    $endpoint = 'https://api.tcms.io/@team/project/pages';

    $mockRequestFactory
        ->shouldReceive('createRequest')
        ->once()
        ->with('GET', $endpoint)
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('withBody')
        ->never();

    $mockStreamFactory
        ->shouldReceive('createStream')
        ->never();

    $mockRequest
        ->shouldReceive('withHeader')
        ->once()
        ->with('Accept', 'application/vnd.api+json')
        ->andReturnSelf();

    $mockRequest
        ->shouldReceive('withHeader')
        ->once()
        ->with('Content-Type', 'application/vnd.api+json')
        ->andReturnSelf();

    $mockClient
        ->shouldReceive('sendRequest')
        ->once()
        ->with($mockRequest)
        ->andReturn($mockResponse);

    $client = new Client($mockClient, $mockRequestFactory, $mockStreamFactory);

    $response = $client->request(
        path: '/@team/project/pages',
        method: HttpMethod::GET,
        body: json_encode(['data' => ['id' => '1']]),
    );

    expect($response)->toBe($mockResponse);
});

it('uses the provided StreamInterface body without creating a new stream', function () {
    $mockClient = mock(ClientInterface::class);
    $mockRequestFactory = mock(RequestFactoryInterface::class);
    $mockStreamFactory = mock(StreamFactoryInterface::class);
    $mockRequest = mock(RequestInterface::class);
    $mockResponse = mock(ResponseInterface::class);
    $providedStream = mock(StreamInterface::class);

    $endpoint = 'https://api.tcms.io/@team/project/pages';

    $mockRequestFactory
        ->shouldReceive('createRequest')
        ->once()
        ->with('POST', $endpoint)
        ->andReturn($mockRequest);

    $mockStreamFactory
        ->shouldReceive('createStream')
        ->never();

    $mockStreamFactory
        ->shouldReceive('createStreamFromResource')
        ->never();

    $mockRequest
        ->shouldReceive('withBody')
        ->once()
        ->with($providedStream)
        ->andReturnSelf();

    $mockRequest
        ->shouldReceive('withHeader')
        ->once()
        ->with('Accept', 'application/vnd.api+json')
        ->andReturnSelf();

    $mockRequest
        ->shouldReceive('withHeader')
        ->once()
        ->with('Content-Type', 'application/vnd.api+json')
        ->andReturnSelf();

    $mockClient
        ->shouldReceive('sendRequest')
        ->once()
        ->with($mockRequest)
        ->andReturn($mockResponse);

    $client = new Client($mockClient, $mockRequestFactory, $mockStreamFactory);

    $response = $client->request(
        path: '/@team/project/pages',
        method: HttpMethod::POST,
        body: $providedStream,
    );

    expect($response)->toBe($mockResponse);
});

it('creates a stream from resource bodies', function () {
    $mockClient = mock(ClientInterface::class);
    $mockRequestFactory = mock(RequestFactoryInterface::class);
    $mockStreamFactory = mock(StreamFactoryInterface::class);
    $mockRequest = mock(RequestInterface::class);
    $mockResponse = mock(ResponseInterface::class);
    $resourceStream = mock(StreamInterface::class);

    $endpoint = 'https://api.tcms.io/@team/project/pages';
    $resource = fopen(filename: 'php://temp', mode: 'r+');

    $mockRequestFactory
        ->shouldReceive('createRequest')
        ->once()
        ->with('POST', $endpoint)
        ->andReturn($mockRequest);

    $mockStreamFactory
        ->shouldReceive('createStreamFromResource')
        ->once()
        ->with($resource)
        ->andReturn($resourceStream);

    $mockStreamFactory
        ->shouldReceive('createStream')
        ->never();

    $mockRequest
        ->shouldReceive('withBody')
        ->once()
        ->with($resourceStream)
        ->andReturnSelf();

    $mockRequest
        ->shouldReceive('withHeader')
        ->once()
        ->with('Accept', 'application/vnd.api+json')
        ->andReturnSelf();

    $mockRequest
        ->shouldReceive('withHeader')
        ->once()
        ->with('Content-Type', 'application/vnd.api+json')
        ->andReturnSelf();

    $mockClient
        ->shouldReceive('sendRequest')
        ->once()
        ->with($mockRequest)
        ->andReturn($mockResponse);

    $client = new Client($mockClient, $mockRequestFactory, $mockStreamFactory);

    try {
        $response = $client->request(
            path: '/@team/project/pages',
            method: HttpMethod::POST,
            body: $resource,
        );

        expect($response)->toBe($mockResponse);
    } finally {
        fclose($resource);
    }
});
