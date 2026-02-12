<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Typdy\StarterKit\Api\Contracts\Client;
use Typdy\StarterKit\Containers\Contracts\Container;
use Typdy\StarterKit\Parsers\Contracts\DocumentParser;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Parsers\Data\Error;
use Typdy\StarterKit\Repositories\Repository;
use Typdy\StarterKit\Resolvers\ModelResolver;
use Typdy\StarterKit\Storage\Helpers\DocumentTransformer;
use Typdy\StarterKit\Sync\Contracts\DriverPipeline;
use Typdy\StarterKit\Sync\Data\Metadata;
use Typdy\StarterKit\Sync\Data\Result;
use Typdy\StarterKit\Tests\Unit\Repositories\Fixtures\TestApiRepo;
use Typdy\StarterKit\Tests\Unit\Repositories\Fixtures\TestDriver;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\TypdyConfig;

beforeEach(function () {
    Typdy::$config = new TypdyConfig(
        team: 'test-team',
        project: 'test-project',
        drivers: [
            TestDriver::class,
        ],
    );

    $this->mockClient = mock(Client::class);
    $this->mockContainer = mock(Container::class);

    $this->mockContainer
        ->shouldReceive('make')
        ->with(Client::class)
        ->andReturn($this->mockClient);

    $this->mockContainer
        ->shouldReceive('make')
        ->with(TestDriver::class, [])
        ->andReturn(new TestDriver());

    $this->mockPipeline = mock(DriverPipeline::class);

    $this->mockContainer
        ->shouldReceive('make')
        ->with(DriverPipeline::class, [])
        ->andReturn($this->mockPipeline)
        ->byDefault();

    $this->mockPipeline
        ->shouldReceive('getMetadata')
        ->andReturn(new Metadata())
        ->byDefault();

    $this->mockPipeline
        ->shouldReceive('read')
        ->andReturn(new Result())
        ->byDefault();

    $this->mockPipeline
        ->shouldReceive('write')
        ->andReturnUsing(fn ($repository, $request, $data) => new Result(
            served: true,
            data: $data,
        ))
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
    Repository::clearMacros();
});

it('registers and executes custom macros', function () {
    $repo = new TestApiRepo();

    $repo->macro('testMacro', fn () => 'macro-result');

    expect($repo->hasMacro('testMacro'))->toBeTrue();
    expect($repo->testMacro())->toBe('macro-result');
});

it('blocks external calls to protected macros', function () {
    $repo = new TestApiRepo();

    expect($repo->hasMacro('resolvePageNumber'))->toBeTrue();

    $repo->resolvePageNumber();
})->throws(BadMethodCallException::class, 'Macro resolvePageNumber is protected and cannot be called');

it('throws when calling a macro that does not exist', function () {
    $repo = new TestApiRepo();

    $repo->missingMacro();
})->throws(BadMethodCallException::class, 'Macro missingMacro does not exist.');

it('uses the throwResponseException override macro when provided', function () {
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

    $mockDocumentParser = mock(DocumentParser::class);

    $mockDocumentParser
        ->shouldReceive('parse')
        ->once()
        ->andReturn(new Document(
            response: new Response(422, [], json_encode($body)),
            errors: [new Error(title: 'Invalid Attribute', detail: 'Title must be at least 3 characters.')],
        ));

    $this->mockContainer
        ->shouldReceive('make')
        ->with(DocumentParser::class, [])
        ->once()
        ->andReturn($mockDocumentParser);

    $repo = new TestApiRepo();

    $repo->macro('throwResponseException', function (Document $document): void {
        throw new LogicException('Custom response exception');
    });

    $repo->all();
})
    ->throws(LogicException::class, 'Custom response exception');

it('uses the throwNotFoundException override macro when findOrFail receives a 404', function () {
    $body = (object) [
        'errors' => [
            (object) [
                'title' => 'Not Found',
                'detail' => 'Missing',
            ],
        ],
    ];

    $this->mockClient
        ->shouldReceive('request')
        ->once()
        ->andReturn(new Response(404, [], json_encode($body)));

    $mockDocumentParser = mock(DocumentParser::class);
    $mockDocumentParser
        ->shouldReceive('parse')
        ->once()
        ->andReturn(new Document(
            response: new Response(404, [], json_encode($body)),
            errors: [new Error(title: 'Not Found', detail: 'Missing')],
        ));

    $this->mockContainer
        ->shouldReceive('make')
        ->with(DocumentParser::class, [])
        ->once()
        ->andReturn($mockDocumentParser);

    $repo = new TestApiRepo();

    $repo->macro('throwNotFoundException', function (): void {
        throw new LogicException('Custom not found exception');
    });

    $repo->returnsOnError()->findOrFail(999);
})->throws(LogicException::class, 'Custom not found exception');

it('uses the resolvePageNumber override macro for paginate defaults', function () {
    $body = (object) [
        'data' => [],
    ];

    $this->mockClient
        ->shouldReceive('request')
        ->once()
        ->withSomeOfArgs(
            path: '@test-team/test-project/tests?all=0&'
            . urlencode('page[number]')
            . '=9&'
            . urlencode('page[size]')
            . '=5',
            useMapi: false,
        )
        ->andReturn(new Response(200, [], json_encode($body)));

    $mockDocumentParser = mock(DocumentParser::class);

    $mockDocumentParser
        ->shouldReceive('parse')
        ->once()
        ->andReturn(new Document(
            response: new Response(200, [], json_encode($body)),
            data: [],
        ));

    $this->mockContainer
        ->shouldReceive('make')
        ->with(DocumentParser::class, [])
        ->once()
        ->andReturn($mockDocumentParser);

    $repo = new TestApiRepo();

    $repo->macro('resolvePageNumber', fn (): int => 9);

    $repo->paginate(5);
});
