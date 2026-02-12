<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Typdy\StarterKit\Api\Contracts\Client;
use Typdy\StarterKit\Containers\Contracts\Container;
use Typdy\StarterKit\Parsers\Contracts\DocumentParser;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Parsers\Data\Error;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Repositories\Exceptions\HttpNotFoundException;
use Typdy\StarterKit\Resolvers\ModelResolver;
use Typdy\StarterKit\Storage\Helpers\DocumentTransformer;
use Typdy\StarterKit\Sync\Contracts\DriverPipeline;
use Typdy\StarterKit\Sync\Data\Metadata;
use Typdy\StarterKit\Sync\Data\Result;
use Typdy\StarterKit\Tests\Unit\Repositories\Fixtures\TestApiRepo;
use Typdy\StarterKit\Tests\Unit\Repositories\Fixtures\TestDriver;
use Typdy\StarterKit\Tests\Unit\Repositories\Fixtures\TestModel;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\TypdyConfig;

beforeEach(function () {
    Typdy::$config = new TypdyConfig(
        team: 'test-team',
        project: 'test-project',
        drivers: [TestDriver::class],
    );

    $this->mockClient = mock(Client::class);
    $this->mockContainer = mock(Container::class);
    $this->mockDocumentParser = mock(DocumentParser::class);

    $this->mockContainer
        ->shouldReceive('make')
        ->with(Client::class)
        ->andReturn($this->mockClient);

    $this->mockContainer
        ->shouldReceive('make')
        ->with(TestDriver::class, [])
        ->andReturn(new TestDriver());

    $this->mockContainer
        ->shouldReceive('make')
        ->with(DocumentParser::class, [])
        ->andReturn($this->mockDocumentParser);

    $this->mockPipeline = mock(DriverPipeline::class);

    $this->mockContainer
        ->shouldReceive('make')
        ->with(DriverPipeline::class, [])
        ->andReturn($this->mockPipeline)
        ->byDefault();

    $this->mockPipeline
        ->shouldReceive('read')
        ->andReturn(new Result())
        ->byDefault();

    $this->mockPipeline
        ->shouldReceive('getMetadata')
        ->andReturn(new Metadata())
        ->byDefault();

    $this->mockModelResolver = mock(ModelResolver::class);

    $this->mockContainer
        ->shouldReceive('make')
        ->with(DocumentTransformer::class, [])
        ->andReturn(new DocumentTransformer($this->mockModelResolver))
        ->byDefault();

    Typdy::$container = $this->mockContainer;
});

afterEach(function () {
    Typdy::$container = null;
    Typdy::$collectCallback = null;
    Typdy::$paginateCallback = null;
});

it('throws when request responses fail in throws mode', function () {
    $body = (object) [
        'errors' => [
            (object) [
                'title' => 'Invalid Attribute',
                'detail' => 'Title must be at least 3 characters.',
                'meta' => (object) ['timestamp' => '2024-06-01T12:00:00Z'],
            ],
        ],
    ];

    $this->mockClient
        ->shouldReceive('request')
        ->once()
        ->andReturn(new Response(422, [], json_encode($body)));

    $this->mockDocumentParser
        ->shouldReceive('parse')
        ->once()
        ->andReturn(new Document(
            response: new Response(422, [], json_encode($body)),
            errors: [new Error(title: 'Invalid Attribute', detail: 'Title must be at least 3 characters.', meta: [
                'timestamp' => '2024-06-01T12:00:00Z',
            ])],
        ));

    new TestApiRepo()->throws()->all();
})
    ->throws(RuntimeException::class, 'Received unsuccessful response from typdy: 422');

it('returns failed documents in returnsOnError mode', function () {
    $body = (object) [
        'errors' => [
            (object) [
                'title' => 'Invalid Attribute',
                'detail' => 'Title must be at least 3 characters.',
            ],
        ],
    ];

    $this->mockClient
        ->shouldReceive('request')
        ->once()
        ->andReturn(new Response(422, [], json_encode($body)));

    $this->mockDocumentParser
        ->shouldReceive('parse')
        ->once()
        ->andReturn(new Document(
            response: new Response(422, [], json_encode($body)),
            errors: [new Error(title: 'Invalid Attribute', detail: 'Title must be at least 3 characters.')],
        ));

    $document = new TestApiRepo()->returnsOnError()->all();

    expect($document)->toBeInstanceOf(Document::class);
    expect($document->failed())->toBeTrue();
    expect($document->errors)->toHaveCount(1);
});

it('resets throw behavior after returnsOnError call', function () {
    $body = (object) ['errors' => [(object) [
        'title' => 'Invalid Attribute',
        'detail' => 'Title must be at least 3 characters.',
    ]]];

    $this->mockClient
        ->shouldReceive('request')
        ->twice()
        ->andReturn(new Response(422, [], json_encode($body)));

    $this->mockDocumentParser
        ->shouldReceive('parse')
        ->twice()
        ->andReturn(new Document(
            response: new Response(422, [], json_encode($body)),
            errors: [new Error(title: 'Invalid Attribute', detail: 'Title must be at least 3 characters.')],
        ));

    $repo = new TestApiRepo();

    $doc = $repo->returnsOnError()->all();

    expect($doc)->toBeInstanceOf(Document::class);

    $repo->all();
})
    ->throws(RuntimeException::class);

it('builds mapi paths with request parameter overrides', function () {
    $this->mockModelResolver
        ->shouldReceive('resolveOne')
        ->once()
        ->withArgs(function ($team, $project, $blueprint) {
            expect($team)->toBe('test-team');
            expect($project)->toBe('test-project');
            expect($blueprint)->toBe('test');

            return true;
        })
        ->andReturn(new TestModel());

    $body = (object) [
        'data' => [
            (object) [
                'id' => '1',
                'type' => 'test',
                'attributes' => (object) ['title' => 'Test Construct'],
            ],
        ],
    ];

    $this->mockClient
        ->shouldReceive('request')
        ->withSomeOfArgs(
            path: '@test-team/test-project/constructs/test?all=0&' . urlencode('page[size]') . '=10',
            useMapi: true,
        )
        ->once()
        ->andReturn(new Response(200, [], json_encode($body)));

    $this->mockDocumentParser
        ->shouldReceive('parse')
        ->once()
        ->andReturn(new Document(
            response: new Response(200, [], json_encode($body)),
            data: [new Resource(id: '1', type: 'test', attributes: ['title' => 'Test Construct'])],
        ));

    $result = new TestApiRepo()->mapi()->all([
        'parameters' => [
            'all' => false,
            'page[size]' => 10,
        ],
    ]);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
});

it('throws not found for 404 responses in throws mode', function () {
    $body = (object) ['errors' => [(object) [
        'title' => 'Not Found',
        'detail' => 'The requested resource was not found.',
    ]]];

    $this->mockClient
        ->shouldReceive('request')
        ->withSomeOfArgs(path: '@test-team/test-project/tests/999', useMapi: false)
        ->once()
        ->andReturn(new Response(404, [], json_encode($body)));

    $this->mockDocumentParser
        ->shouldReceive('parse')
        ->once()
        ->andReturn(new Document(
            response: new Response(404, [], json_encode($body)),
            errors: [new Error(title: 'Not Found', detail: 'The requested resource was not found.')],
        ));

    new TestApiRepo()->find(999);
})
    ->throws(HttpNotFoundException::class, 'Construct not found.');

it('returns null for 404 responses in returnsOnError mode', function () {
    $body = (object) ['errors' => [(object) [
        'title' => 'Not Found',
        'detail' => 'The requested resource was not found.',
    ]]];

    $this->mockClient
        ->shouldReceive('request')
        ->withSomeOfArgs(path: '@test-team/test-project/tests/999', useMapi: false)
        ->once()
        ->andReturn(new Response(404, [], json_encode($body)));

    $this->mockDocumentParser
        ->shouldReceive('parse')
        ->once()
        ->andReturn(new Document(
            response: new Response(404, [], json_encode($body)),
            errors: [new Error(title: 'Not Found', detail: 'The requested resource was not found.')],
        ));

    $result = new TestApiRepo()
        ->returnsOnError()
        ->find(999);

    expect($result)->toBeNull();
});
