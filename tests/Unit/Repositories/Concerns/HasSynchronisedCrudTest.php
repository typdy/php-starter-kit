<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Typdy\StarterKit\Api\Contracts\Client;
use Typdy\StarterKit\Api\Enums\HttpMethod;
use Typdy\StarterKit\Containers\Contracts\Container;
use Typdy\StarterKit\Contracts\PaginateCallback;
use Typdy\StarterKit\Data\Paginated;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Parsers\Contracts\DocumentParser;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Parsers\Data\Error;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Repositories\Data\Request;
use Typdy\StarterKit\Resolvers\ModelResolver;
use Typdy\StarterKit\Storage\Helpers\DocumentTransformer;
use Typdy\StarterKit\Sync\Contracts\DriverPipeline;
use Typdy\StarterKit\Sync\Data\Metadata;
use Typdy\StarterKit\Sync\Data\Result;
use Typdy\StarterKit\Tests\Unit\Repositories\Fixtures\TestApiRepo;
use Typdy\StarterKit\Tests\Unit\Repositories\Fixtures\TestDatabaseDriver;
use Typdy\StarterKit\Tests\Unit\Repositories\Fixtures\TestDriver;
use Typdy\StarterKit\Tests\Unit\Repositories\Fixtures\TestModel;
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

it('returns a construct from storage when the pipeline can satisfy find', function () {
    $construct = new TestModel()->hydrateFromResource(new Resource(
        id: '1',
        type: 'test',
        attributes: [
            'title' => 'Test Construct',
        ],
    ));

    $this->mockPipeline
        ->shouldReceive('read')
        ->once()
        ->andReturn(new Result(
            served: true,
            data: $construct,
        ));

    $repo = new TestApiRepo();

    $result = $repo->find(1);

    expect($result)->toBeInstanceOf(TestModel::class);
    expect($result->id)->toBe(1);
    expect($result->getBlueprint())->toBe('test');
    expect($result->title)->toBe('Test Construct');
});

it('returns a collection from storage when the pipeline can satisfy all', function () {
    $this->mockPipeline
        ->shouldReceive('read')
        ->once()
        ->andReturn(new Result(
            served: true,
            data: [
                new TestModel()->hydrateFromResource(new Resource(
                    id: '1',
                    type: 'test',
                    attributes: [
                        'title' => 'Test Construct',
                    ],
                )),
            ],
        ));

    $repo = new TestApiRepo();

    $collection = $repo->all();

    expect($collection)->toBeArray();
    expect($collection)->toHaveCount(1);
    expect($collection[0])->toBeInstanceOf(TestModel::class);
});

it('syncs typdy responses through the pipeline when storage misses', function () {
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
        'data' => (object) [
            'id' => '1',
            'type' => 'test',
            'attributes' => (object) [
                'identifier' => '1',
                'title' => 'Test Construct',
            ],
        ],
    ];

    $this->mockClient
        ->shouldReceive('request')
        ->once()
        ->andReturn(new Response(200, [], json_encode($body)));

    $mockDocumentParser = mock(DocumentParser::class);
    $mockDocumentParser
        ->shouldReceive('parse')
        ->once()
        ->andReturn(new Document(
            response: new Response(200, [], json_encode($body)),
            data: new Resource(
                id: '1',
                type: 'test',
                attributes: [
                    'title' => 'Test Construct',
                ],
            ),
        ));

    $mockDriver = mock(TestDriver::class);

    $this->mockContainer
        ->shouldReceive('make')
        ->with(TestDriver::class, [])
        ->once()
        ->andReturn($mockDriver);

    $this->mockContainer
        ->shouldReceive('make')
        ->with(DocumentParser::class, [])
        ->once()
        ->andReturn($mockDocumentParser);

    $this->mockPipeline
        ->shouldReceive('read')
        ->once()
        ->andReturn(new Result());

    $this->mockPipeline
        ->shouldReceive('write')
        ->once()
        ->withArgs(function ($repository, $request, $data) {
            expect($repository)->toBeInstanceOf(TestApiRepo::class);
            expect($request)->toBeInstanceOf(Request::class);
            expect($data)->toBeInstanceOf(Construct::class);
            expect($data->title)->toBe('Test Construct');

            return true;
        })
        ->andReturnUsing(fn ($repository, $request, $data) => new Result(
            served: true,
            data: $data,
        ));

    $repo = new TestApiRepo();

    $result = $repo->find(1);

    expect($result)->toBeInstanceOf(Construct::class);
    expect($result->id)->toBe(1);
    expect($result->identifier)->toBeNull();
    expect($result->title)->toBe('Test Construct');
});

it('does not call typdy sync when a database driver is present', function () {
    Typdy::$config = new TypdyConfig(
        team: 'test-team',
        project: 'test-project',
        drivers: [
            TestDriver::class,
            TestDatabaseDriver::class,
        ],
    );

    $this->mockPipeline
        ->shouldReceive('read')
        ->once()
        ->andReturn(new Result());

    $this->mockClient->shouldNotReceive('request');

    $repo = new TestApiRepo();

    $repo->find(1);
});

it('does not delete from drivers when remote delete fails in returnsOnError mode', function () {
    $mockDocumentParser = mock(DocumentParser::class);

    $this->mockContainer
        ->shouldReceive('make')
        ->with(DocumentParser::class, [])
        ->andReturn($mockDocumentParser);

    $body = (object) [
        'errors' => [
            (object) [
                'title' => 'Service Unavailable',
                'detail' => 'Typdy is temporarily unavailable.',
            ],
        ],
    ];

    $this->mockClient
        ->shouldReceive('request')
        ->once()
        ->andReturn(new Response(503, [], json_encode($body)));

    $mockDocumentParser
        ->shouldReceive('parse')
        ->once()
        ->andReturn(new Document(
            response: new Response(503, [], json_encode($body)),
            errors: [new Error(title: 'Service Unavailable', detail: 'Typdy is temporarily unavailable.')],
        ));

    $mockDriver = mock(TestDriver::class);
    $mockDriver->shouldNotReceive('delete');

    $this->mockContainer
        ->shouldNotReceive('make')
        ->with(TestDriver::class, []);

    $repo = new TestApiRepo();

    $repo->returnsOnError()->delete(1);
});

it('deletes from drivers through the pipeline when remote delete succeeds', function () {
    $mockDocumentParser = mock(DocumentParser::class);

    $this->mockClient
        ->shouldReceive('request')
        ->once()
        ->andReturn(new Response(204));

    $this->mockContainer
        ->shouldReceive('make')
        ->with(DocumentParser::class, [])
        ->andReturn($mockDocumentParser);

    $this->mockPipeline
        ->shouldReceive('delete')
        ->once()
        ->withArgs(function ($repository, $request) {
            expect($repository)->toBeInstanceOf(TestApiRepo::class);
            expect($request)->toBeInstanceOf(Request::class);
            expect($request->id)->toBe(1);

            return true;
        })
        ->andReturn(new Result(served: true));

    $repo = new TestApiRepo();

    $repo->delete(1);
});

it('throws when save receives an unexpected sync payload type', function () {
    $this->mockClient
        ->shouldReceive('request')
        ->once()
        ->andReturn(new Response(200, [], null));

    $mockDocumentParser = mock(DocumentParser::class);

    $this->mockContainer
        ->shouldReceive('make')
        ->with(DocumentParser::class, [])
        ->andReturn($mockDocumentParser);

    $mockDriver = mock(TestDriver::class);

    $this->mockContainer
        ->shouldReceive('make')
        ->with(TestDriver::class, [])
        ->once()
        ->andReturn($mockDriver);

    $this->mockPipeline->shouldNotReceive('write');

    $repo = new TestApiRepo();

    $model = new TestModel();

    $repo->save($model);
})
    ->throws(RuntimeException::class, 'Save operation returned an unexpected result type.');

it('adapts repository pagination results using the registered callback', function () {
    $items = [
        new TestModel()->hydrateFromResource(new Resource(
            id: '1',
            type: 'test',
            attributes: ['title' => 'One'],
        )),
    ];

    $this->mockPipeline
        ->shouldReceive('read')
        ->once()
        ->andReturn(new Result(
            served: true,
            data: $items,
        ));

    Typdy::$paginateCallback = new class() implements PaginateCallback {
        public function __invoke(Paginated $models, Request $request): iterable
        {
            return new LengthAwarePaginator(
                $models->items,
                $models->total,
                $models->perPage,
                $models->currentPage,
            );
        }
    };

    $repo = new TestApiRepo();

    $paginated = $repo->paginate(5, ['parameters' => ['page[number]' => 2]]);

    expect($paginated)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($paginated->total())->toBe(1);
    expect($paginated->perPage())->toBe(5);
    expect($paginated->currentPage())->toBe(2);
    expect($paginated->lastPage())->toBe(1);
});

it('returns a model when save creates a new construct', function () {
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

    $mockDocumentParser = mock(DocumentParser::class);

    $this->mockContainer
        ->shouldReceive('make')
        ->once()
        ->with(DocumentParser::class, [])
        ->andReturn($mockDocumentParser);

    $body = (object) [
        'data' => (object) [
            'id' => '101',
            'type' => 'test',
            'attributes' => (object) [
                'title' => 'Created',
            ],
        ],
    ];

    $this->mockClient
        ->shouldReceive('request')
        ->once()
        ->withSomeOfArgs(
            path: '@test-team/test-project/constructs/test',
            method: HttpMethod::POST,
            useMapi: true,
        )
        ->andReturn(new Response(201, [], json_encode($body)));

    $mockDocumentParser
        ->shouldReceive('parse')
        ->once()
        ->andReturn(new Document(
            response: new Response(201, [], json_encode($body)),
            data: new Resource(id: '101', type: 'test', attributes: ['title' => 'Created']),
        ));

    $mockDriver = mock(TestDriver::class);

    $this->mockContainer
        ->shouldReceive('make')
        ->with(TestDriver::class, [])
        ->once()
        ->andReturn($mockDriver);

    $this->mockPipeline
        ->shouldReceive('write')
        ->once()
        ->andReturnUsing(fn ($repository, $request, $data) => new Result(
            served: true,
            data: $data,
        ));

    $repo = new TestApiRepo();

    $model = new TestModel();
    $model->title = 'Created';

    $result = $repo->save($model);

    expect($result)->toBeInstanceOf(TestModel::class);
    expect($result->id)->toBe(101);
});

it('returns a model when save updates an existing construct', function () {
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

    $mockDocumentParser = mock(DocumentParser::class);

    $this->mockContainer
        ->shouldReceive('make')
        ->once()
        ->with(DocumentParser::class, [])
        ->andReturn($mockDocumentParser);

    $body = (object) [
        'data' => (object) [
            'id' => '5',
            'type' => 'test',
            'attributes' => (object) [
                'title' => 'Updated',
            ],
        ],
    ];

    $this->mockClient
        ->shouldReceive('request')
        ->once()
        ->withSomeOfArgs(
            path: '@test-team/test-project/constructs/test/5',
            method: HttpMethod::PATCH,
            useMapi: true,
        )
        ->andReturn(new Response(200, [], json_encode($body)));

    $mockDocumentParser
        ->shouldReceive('parse')
        ->once()
        ->andReturn(new Document(
            response: new Response(200, [], json_encode($body)),
            data: new Resource(id: '5', type: 'test', attributes: ['title' => 'Updated']),
        ));

    $mockDriver = mock(TestDriver::class);

    $this->mockContainer
        ->shouldReceive('make')
        ->with(TestDriver::class, [])
        ->once()
        ->andReturn($mockDriver);

    $this->mockPipeline
        ->shouldReceive('write')
        ->once()
        ->andReturnUsing(fn ($repository, $request, $data) => new Result(
            served: true,
            data: $data,
        ));

    $repo = new TestApiRepo();

    $model = new TestModel()->hydrateFromResource(new Resource(
        id: '5',
        type: 'test',
        attributes: ['title' => 'Old'],
    ));
    $model->title = 'Updated';

    $result = $repo->save($model);

    expect($result)->toBeInstanceOf(TestModel::class);
    expect($result->id)->toBe(5);
});

it('returns a failed document when paginate falls back to typdy and response fails', function () {
    $this->mockPipeline
        ->shouldReceive('read')
        ->once()
        ->andReturn(new Result());

    $mockDocumentParser = mock(DocumentParser::class);
    $this->mockContainer
        ->shouldReceive('make')
        ->with(DocumentParser::class, [])
        ->once()
        ->andReturn($mockDocumentParser);

    $body = (object) ['errors' => [(object) ['title' => 'Unavailable', 'detail' => 'Down']]];

    $this->mockClient
        ->shouldReceive('request')
        ->once()
        ->andReturn(new Response(503, [], json_encode($body)));

    $mockDocumentParser
        ->shouldReceive('parse')
        ->once()
        ->andReturn(new Document(
            response: new Response(503, [], json_encode($body)),
            errors: [new Error(title: 'Unavailable', detail: 'Down')],
        ));

    $result = new TestApiRepo()
        ->returnsOnError()
        ->paginate(10);

    expect($result)->toBeInstanceOf(Document::class);
    expect($result->failed())->toBeTrue();
});

it('normalizes invalid page values when building pagination', function () {
    $this->mockPipeline
        ->shouldReceive('read')
        ->once()
        ->andReturn(new Result(
            served: true,
            data: [],
        ));

    Typdy::$paginateCallback = new class() implements PaginateCallback {
        public function __invoke(Paginated $models, Request $request): iterable
        {
            return $models;
        }
    };

    $result = new TestApiRepo()->paginate(0, ['parameters' => ['page[number]' => -3]]);

    expect($result)->toBeInstanceOf(Paginated::class);
    expect($result->total)->toBe(0);
    expect($result->perPage)->toBe(1);
    expect($result->currentPage)->toBe(1);
    expect($result->lastPage)->toBe(1);
});

it('does not call typdy for paginate when a database driver is present', function () {
    Typdy::$config = new TypdyConfig(
        team: 'test-team',
        project: 'test-project',
        drivers: [
            TestDriver::class,
            TestDatabaseDriver::class,
        ],
    );

    $this->mockPipeline
        ->shouldReceive('read')
        ->once()
        ->andReturn(new Result());

    $this->mockClient->shouldNotReceive('request');

    $result = new TestApiRepo()->paginate(10);

    expect($result)->toBeInstanceOf(Paginated::class);
    expect($result->total)->toBe(0);
});

it('adapts paginated results when pagination falls back to typdy sync', function () {
    $this->mockModelResolver
        ->shouldReceive('resolveOne')
        ->twice()
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
                'attributes' => (object) ['title' => 'One'],
            ],
            (object) [
                'id' => '2',
                'type' => 'test',
                'attributes' => (object) ['title' => 'Two'],
            ],
        ],
    ];

    $this->mockClient
        ->shouldReceive('request')
        ->once()
        ->withSomeOfArgs(
            path: '@test-team/test-project/tests?all=0&'
            . urlencode('page[number]')
            . '=2&'
            . urlencode('page[size]')
            . '=1',
            useMapi: false,
        )
        ->andReturn(new Response(200, [], json_encode($body)));

    $mockDocumentParser = mock(DocumentParser::class);
    $mockDocumentParser
        ->shouldReceive('parse')
        ->once()
        ->andReturn(new Document(
            response: new Response(200, [], json_encode($body)),
            data: [
                new Resource(id: '1', type: 'test', attributes: ['title' => 'One']),
                new Resource(id: '2', type: 'test', attributes: ['title' => 'Two']),
            ],
        ));

    $this->mockContainer
        ->shouldReceive('make')
        ->with(DocumentParser::class, [])
        ->once()
        ->andReturn($mockDocumentParser);

    $mockDriver = mock(TestDriver::class);

    $this->mockContainer
        ->shouldReceive('make')
        ->with(TestDriver::class, [])
        ->once()
        ->andReturn($mockDriver);

    $this->mockPipeline
        ->shouldReceive('read')
        ->once()
        ->andReturn(new Result());

    $this->mockPipeline
        ->shouldReceive('write')
        ->once()
        ->andReturnUsing(fn ($repository, $request, $data) => new Result(
            served: true,
            data: $data,
        ));

    Typdy::$paginateCallback = new class() implements PaginateCallback {
        public function __invoke(Paginated $models, Request $request): iterable
        {
            return new LengthAwarePaginator(
                $models->items,
                $models->total,
                $models->perPage,
                $models->currentPage,
            );
        }
    };

    $repo = new TestApiRepo();

    $result = $repo->paginate(1, ['parameters' => ['page[number]' => 2]]);

    expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($result->total())->toBe(2);
    expect($result->perPage())->toBe(1);
    expect($result->currentPage())->toBe(2);
    expect($result->lastPage())->toBe(2);
});
