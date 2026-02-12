<?php

declare(strict_types=1);

use Typdy\StarterKit\Attributes\Blueprint;
use Typdy\StarterKit\Containers\Contracts\Container;
use Typdy\StarterKit\Models\Model;
use Typdy\StarterKit\Parsers\Data\Relation;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\Data\Request;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesModels;
use Typdy\StarterKit\Storage\Helpers\DocumentTransformer;
use Typdy\StarterKit\Storage\Json\JsonDriver;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\TypdyConfig;

beforeEach(function () {
    $this->storagePath = sprintf('%s/typdy-json-driver-tests-%s', sys_get_temp_dir(), uniqid('', more_entropy: true));

    Typdy::$config = new TypdyConfig(
        team: 'team',
        project: 'project',
        privateStoragePath: $this->storagePath,
    );

    $this->makeRepository = fn (string $signature = 'team:project:test') => mock(Collection::class)
        ->shouldReceive('getTeam')
        ->andReturn('team')
        ->getMock()
        ->shouldReceive('getProject')
        ->andReturn('project')
        ->getMock()
        ->shouldReceive('getBlueprint')
        ->andReturn('test')
        ->getMock()
        ->shouldReceive('isGlobal')
        ->andReturnFalse()
        ->getMock()
        ->shouldReceive('getSignature')
        ->andReturn($signature)
        ->getMock();

    $this->makeResolver = function () {
        $resolver = mock(ResolvesModels::class);

        $resolver
            ->shouldReceive('resolveOne')
            ->andReturnUsing(
                fn () => new
                    #[Blueprint('test')]
                    class extends Model {
                        public ?string $title = null;
                    },
            );

        return $resolver;
    };

    $this->makeModel = function (
        int $id,
        string $identifier,
        string $title,
        array $relationships = [],
    ) {
        $resource = new Resource(
            type: 'test',
            id: (string) $id,
            attributes: [
                'identifier' => $identifier,
                'title' => $title,
            ],
            relationships: $relationships,
        );

        $model = new
            #[Blueprint('test')]
            class extends Model {
                public ?string $title = null;
            };

        return $model->hydrateFromResource($resource);
    };
});

afterEach(function () {
    Typdy::$container = null;

    if (!file_exists($this->storagePath)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->storagePath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $path) {
        if ($path->isDir()) {
            rmdir($path->getPathname());

            continue;
        }

        unlink($path->getPathname());
    }

    rmdir($this->storagePath);
});

it('writes and resolves constructs by id or identifier', function () {
    $driver = new JsonDriver(new DocumentTransformer(($this->makeResolver)()));

    $repository = ($this->makeRepository)();

    $request = new Request(
        team: 'team',
        project: 'project',
        id: 1,
    );

    $model = ($this->makeModel)(id: 1, identifier: 'hello-world', title: 'Hello');

    $driver->sync($repository, $request, $model);

    $byId = $driver->find($repository, $request->cloneWithIdOnly(1));
    $byIdentifier = $driver->find($repository, $request->cloneWithIdentifierOnly('hello-world'));

    expect($byId->id)->toBe(1);
    expect($byIdentifier->identifier)->toBe('hello-world');
});

it('writes collections and retrieves them for matching requests', function () {
    $driver = new JsonDriver(new DocumentTransformer(($this->makeResolver)()));

    $repository = ($this->makeRepository)();

    $request = new Request(
        team: 'team',
        project: 'project',
        query: ['parameters' => ['all' => true]],
    );

    $models = [
        ($this->makeModel)(id: 1, identifier: 'one', title: 'One'),
        ($this->makeModel)(id: 2, identifier: 'two', title: 'Two'),
    ];

    $driver->sync($repository, $request, $models);

    $all = $driver->all($repository, $request);

    expect($all)->toBeIterable();
    expect(array_values((array) $all))->toHaveCount(2);
});

it('updates blueprint and id indexes when syncing constructs', function () {
    $mockResolver = mock(ResolvesModels::class);
    $mockResolver
        ->shouldReceive('resolveOne')
        ->once()
        ->with('team', 'project', 'category')
        ->andReturn(new
            #[Blueprint('category')]
            class extends Model {});

    $mockContainer = mock(Container::class);
    $mockContainer
        ->shouldReceive('make')
        ->with(ResolvesModels::class, [])
        ->andReturn($mockResolver);

    Typdy::$container = $mockContainer;

    $driver = new JsonDriver(new DocumentTransformer(($this->makeResolver)()));

    $repository = ($this->makeRepository)();

    $request = new Request(
        team: 'team',
        project: 'project',
        blueprint: 'test',
    );

    $relation = new Relation(
        name: 'category',
        data: new Resource(type: 'category', id: '99'),
    );

    $model = ($this->makeModel)(
        id: 12,
        identifier: 'slug-12',
        title: 'Indexed',
        relationships: ['category' => $relation],
    );

    $driver->sync($repository, $request, $model);

    $byBlueprint = $driver->getIndexByBlueprint($repository, 'test');
    $byId = $driver->getIndexById($repository, 12);
    $relatedId = $driver->getIndexById($repository, 99);

    expect($byBlueprint)->not->toBeEmpty();
    expect($byId)->not->toBeEmpty();
    expect($relatedId)->not->toBeEmpty();
    expect(count($byId))->toBeGreaterThanOrEqual(2);
});

it('syncs generator-backed collections safely', function () {
    $driver = new JsonDriver(new DocumentTransformer(($this->makeResolver)()));

    $repository = ($this->makeRepository)();

    $models = [
        ($this->makeModel)(id: 1, identifier: 'one', title: 'One'),
        ($this->makeModel)(id: 2, identifier: 'two', title: 'Two'),
    ];

    $generator = (function () use ($models) {
        foreach ($models as $model) {
            yield $model;
        }
    })();

    $request = new Request(
        team: 'team',
        project: 'project',
        blueprint: 'test',
        query: ['parameters' => ['all' => true]],
    );

    $result = $driver->sync($repository, $request, $generator);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(2);

    $all = $driver->all($repository, $request);

    expect($all)->toBeIterable();
    expect(array_values((array) $all))->toHaveCount(2);
});

it('keeps index entries stable across repeated writes for the same construct', function () {
    $driver = new JsonDriver(new DocumentTransformer(($this->makeResolver)()));

    $repository = ($this->makeRepository)();

    $request = new Request(
        team: 'team',
        project: 'project',
    );

    $model = ($this->makeModel)(id: 7, identifier: 'same-construct', title: 'Stable');

    $driver->sync($repository, $request->cloneWithQuery(['parameters' => ['include' => 'author']]), $model);
    $driver->sync($repository, $request->cloneWithQuery(['parameters' => ['include' => 'author,category']]), $model);
    $driver->sync($repository, $request->cloneWithQuery(['parameters' => ['fields[test]' => 'title']]), $model);

    $byId = $driver->getIndexById($repository, 7);

    expect($byId)->not->toBeEmpty();
    expect($byId)->toBe(array_values(array_unique($byId)));
    expect(count($byId))->toBeGreaterThanOrEqual(3);
});

it('keeps the index file valid json after many updates', function () {
    $driver = new JsonDriver(new DocumentTransformer(($this->makeResolver)()));

    $repository = ($this->makeRepository)();

    $request = new Request(
        team: 'team',
        project: 'project',
    );

    for ($id = 1; $id <= 20; $id++) {
        $driver->sync($repository, $request, ($this->makeModel)(
            id: $id,
            identifier: 'entry-' . $id,
            title: 'Entry ' . $id,
        ));
    }

    $indexPath = $this->storagePath . '/json/index.json';

    expect($indexPath)->toBeFile();

    $decoded = json_decode((string) file_get_contents($indexPath), associative: true, flags: JSON_THROW_ON_ERROR);

    expect($decoded)->toBeArray();
    expect($decoded)->toHaveKey('constructs');
    expect($decoded)->toHaveKey('blueprints');
});

it('indexes all related resources when relationships contain arrays', function () {
    $mockContainer = mock(Container::class);
    $mockContainer
        ->shouldReceive('make')
        ->with(ResolvesModels::class, [])
        ->twice()
        ->andReturnUsing(function () {
            $resolver = mock(ResolvesModels::class);

            $resolver
                ->shouldReceive('resolveOne')
                ->with('team', 'project', 'category')
                ->once()
                ->andReturn(new
                    #[Blueprint('category')]
                    class extends Model {});

            return $resolver;
        });

    Typdy::$container = $mockContainer;

    $driver = new JsonDriver(new DocumentTransformer(($this->makeResolver)()));

    $repository = ($this->makeRepository)();

    $request = new Request(
        team: 'team',
        project: 'project',
    );

    $relation = new Relation(
        name: 'categories',
        data: [
            new Resource(type: 'category', id: '201'),
            new Resource(type: 'category', id: '202'),
        ],
    );

    $model = ($this->makeModel)(
        id: 55,
        identifier: 'with-many-relations',
        title: 'Multi related',
        relationships: ['categories' => $relation],
    );

    $driver->sync($repository, $request, $model);

    expect($driver->getIndexById($repository, 201))->not->toBeEmpty();
    expect($driver->getIndexById($repository, 202))->not->toBeEmpty();
    expect($driver->getIndexByBlueprint($repository, 'category'))->not->toBeEmpty();
});

it('deletes cached construct files and index entries by id', function () {
    $driver = new JsonDriver(new DocumentTransformer(($this->makeResolver)()));

    $repository = ($this->makeRepository)();

    $request = new Request(
        team: 'team',
        project: 'project',
        id: 44,
    );

    $model = ($this->makeModel)(id: 44, identifier: 'delete-me', title: 'Delete me');

    $driver->sync($repository, $request, $model);

    expect($driver->find($repository, $request))->not->toBeNull();
    expect($driver->getIndexById($repository, 44))->not->toBeEmpty();

    $driver->delete($repository, $request);

    expect($driver->find($repository, $request))->toBeNull();
    expect($driver->getIndexById($repository, 44))->toBeEmpty();
});

it('persists metadata and allows metadata retrieval for constructs and collections', function () {
    $driver = new JsonDriver(new DocumentTransformer(($this->makeResolver)()));

    $repository = ($this->makeRepository)();

    $constructRequest = new Request(
        team: 'team',
        project: 'project',
        id: 91,
    );

    $collectionRequest = new Request(
        team: 'team',
        project: 'project',
        query: ['parameters' => ['all' => true]],
    );

    $model = ($this->makeModel)(id: 91, identifier: 'meta-91', title: 'Meta');

    $driver->sync($repository, $constructRequest, $model);
    $driver->sync($repository, $collectionRequest, [$model]);

    $constructMeta = $driver->getMetadata($repository, $constructRequest);
    $collectionMeta = $driver->getMetadata($repository, $collectionRequest);

    expect($constructMeta)->not->toBeNull();
    expect($constructMeta?->fetchedAt)->not->toBeNull();

    expect($collectionMeta)->not->toBeNull();
    expect($collectionMeta?->fetchedAt)->not->toBeNull();
});

it('uses directional index-only invalidation when deleting a construct', function () {
    $mockResolver = mock(ResolvesModels::class);
    $mockResolver
        ->shouldReceive('resolveOne')
        ->with('team', 'project', 'test')
        ->andReturn(new
            #[Blueprint('test')]
            class extends Model {
                public ?string $title = null;
            });

    $mockContainer = mock(Container::class);
    $mockContainer
        ->shouldReceive('make')
        ->with(ResolvesModels::class, [])
        ->andReturn($mockResolver);

    Typdy::$container = $mockContainer;

    $driver = new JsonDriver(new DocumentTransformer(($this->makeResolver)()));

    $repository = ($this->makeRepository)();

    $request1 = new Request(
        team: 'team',
        project: 'project',
        id: 1,
    );

    $request99 = new Request(
        team: 'team',
        project: 'project',
        id: 99,
    );

    $related = ($this->makeModel)(id: 99, identifier: 'related-99', title: 'Related');

    $relation = new Relation(
        name: 'related',
        data: new Resource(type: 'test', id: '99'),
    );

    $root = ($this->makeModel)(
        id: 1,
        identifier: 'root-1',
        title: 'Root',
        relationships: ['related' => $relation],
    );

    $driver->sync($repository, $request99, $related);
    $driver->sync($repository, $request1, $root);

    expect($driver->find($repository, $request1))->not->toBeNull();
    expect($driver->find($repository, $request99))->not->toBeNull();

    $driver->delete($repository, $request1);

    expect($driver->find($repository, $request1))->toBeNull();
    expect($driver->find($repository, $request99))->not->toBeNull();
});

it('invalidates reverse-related caches when reverse relation data is explicitly cached', function () {
    $mockResolver = mock(ResolvesModels::class);
    $mockResolver
        ->shouldReceive('resolveOne')
        ->with('team', 'project', 'test')
        ->andReturn(new
            #[Blueprint('test')]
            class extends Model {
                public ?string $title = null;
            });

    $mockContainer = mock(Container::class);
    $mockContainer
        ->shouldReceive('make')
        ->with(ResolvesModels::class, [])
        ->andReturn($mockResolver);

    Typdy::$container = $mockContainer;

    $driver = new JsonDriver(new DocumentTransformer(($this->makeResolver)()));

    $repository = ($this->makeRepository)();

    $request99 = new Request(
        team: 'team',
        project: 'project',
        id: 99,
    );

    $request1 = new Request(
        team: 'team',
        project: 'project',
        id: 1,
    );

    $root = ($this->makeModel)(id: 1, identifier: 'root-1', title: 'Root');

    $reverseRelation = new Relation(
        name: 'page',
        data: new Resource(type: 'test', id: '1'),
    );

    $relatedWithReverse = ($this->makeModel)(
        id: 99,
        identifier: 'related-99',
        title: 'Related With Reverse',
        relationships: ['page' => $reverseRelation],
    );

    $driver->sync($repository, $request1, $root);
    $driver->sync($repository, $request99, $relatedWithReverse);

    expect($driver->find($repository, $request1))->not->toBeNull();
    expect($driver->find($repository, $request99))->not->toBeNull();

    $driver->delete($repository, $request1);

    expect($driver->find($repository, $request1))->toBeNull();
    expect($driver->find($repository, $request1))->toBeNull();
});

it('mutates complete collection payloads when mutation strategy is enabled', function () {
    Typdy::$config = new TypdyConfig(
        team: 'team',
        project: 'project',
        privateStoragePath: $this->storagePath,
        mutateStoredPayloads: true,
    );

    $driver = new JsonDriver(new DocumentTransformer(($this->makeResolver)()));

    $repository = ($this->makeRepository)();

    $request = new Request(
        team: 'team',
        project: 'project',
        query: ['parameters' => ['all' => true]],
    );

    $model = ($this->makeModel)(id: 21, identifier: 'mutable-21', title: 'Original');

    $driver->sync($repository, $request, [$model]);

    $updated = ($this->makeModel)(id: 21, identifier: 'mutable-21', title: 'Updated');

    $driver->sync($repository, $request, $updated);

    $all = $driver->all($repository, $request);

    expect($all)->toBeIterable();
    expect(array_values((array) $all))->toHaveCount(1);
    expect(array_values((array) $all)[0]->title)->toBe('Updated');
});

it('invalidates partial collection payloads when mutation strategy is enabled', function () {
    Typdy::$config = new TypdyConfig(
        team: 'team',
        project: 'project',
        privateStoragePath: $this->storagePath,
        mutateStoredPayloads: true,
    );

    $driver = new JsonDriver(new DocumentTransformer(($this->makeResolver)()));

    $repository = ($this->makeRepository)();

    $request = new Request(
        team: 'team',
        project: 'project',
        query: ['parameters' => ['all' => false, 'page[number]' => 1]],
    );

    $model = ($this->makeModel)(id: 22, identifier: 'mutable-22', title: 'Original');

    $driver->sync($repository, $request, [$model]);

    $updated = ($this->makeModel)(id: 22, identifier: 'mutable-22', title: 'Updated');

    $driver->sync($repository, $request, $updated);

    expect($driver->all($repository, $request))->toBeNull();
});

it('mutates included resources when cached payload contains target construct in includes', function () {
    Typdy::$config = new TypdyConfig(
        team: 'team',
        project: 'project',
        privateStoragePath: $this->storagePath,
        mutateStoredPayloads: true,
    );

    $driver = new JsonDriver(new DocumentTransformer(($this->makeResolver)()));

    $repository = ($this->makeRepository)();

    $target = ($this->makeModel)(id: 33, identifier: 'include-33', title: 'Original');

    $request = new Request(
        team: 'team',
        project: 'project',
        query: ['parameters' => ['all' => true, 'include' => 'test']],
    );

    $driver->sync($repository, $request, [$target]);

    $paths = $driver->getIndexById($repository, 33);

    $collectionRelativePath = null;

    foreach ($paths as $relativePath) {
        if (!str_contains($relativePath, 'collections/')) {
            continue;
        }

        $collectionRelativePath = $relativePath;
        break;
    }

    expect($collectionRelativePath)->not->toBeNull();

    $absolutePath = $this->storagePath . '/json/' . $collectionRelativePath;

    $payload = json_decode((string) file_get_contents($absolutePath), associative: true, flags: JSON_THROW_ON_ERROR);

    $payload['constructs'] = [];
    $payload['included'] = [
        [
            'type' => 'test',
            'id' => '33',
            'attributes' => [
                'identifier' => 'include-33',
                'title' => 'Original',
            ],
        ],
    ];

    file_put_contents($absolutePath, json_encode($payload, JSON_THROW_ON_ERROR));

    $updated = ($this->makeModel)(id: 33, identifier: 'include-33', title: 'Updated');

    $driver->sync($repository, $request, $updated);

    $mutatedPayload = json_decode(
        (string) file_get_contents($absolutePath),
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($mutatedPayload['included'][0]['attributes']['title'])->toBe('Updated');
});

it('invalidates unsafe partial collection payloads even when included also matches target', function () {
    Typdy::$config = new TypdyConfig(
        team: 'team',
        project: 'project',
        privateStoragePath: $this->storagePath,
        mutateStoredPayloads: true,
    );

    $driver = new JsonDriver(new DocumentTransformer(($this->makeResolver)()));

    $repository = ($this->makeRepository)();

    $target = ($this->makeModel)(id: 34, identifier: 'include-34', title: 'Original');

    $request = new Request(
        team: 'team',
        project: 'project',
        query: ['parameters' => ['all' => false, 'page[number]' => 1, 'include' => 'test']],
    );

    $driver->sync($repository, $request, [$target]);

    $paths = $driver->getIndexById($repository, 34);

    $collectionRelativePath = null;

    foreach ($paths as $relativePath) {
        if (!str_contains($relativePath, 'collections/')) {
            continue;
        }

        $collectionRelativePath = $relativePath;
        break;
    }

    expect($collectionRelativePath)->not->toBeNull();

    $absolutePath = $this->storagePath . '/json/' . $collectionRelativePath;

    $payload = json_decode((string) file_get_contents($absolutePath), associative: true, flags: JSON_THROW_ON_ERROR);

    $payload['included'] = [
        [
            'type' => 'test',
            'id' => '34',
            'attributes' => [
                'identifier' => 'include-34',
                'title' => 'Original',
            ],
        ],
    ];

    file_put_contents($absolutePath, json_encode($payload, JSON_THROW_ON_ERROR));

    Typdy::$config = new TypdyConfig(
        team: 'team',
        project: 'project',
        privateStoragePath: $this->storagePath,
        mutateStoredPayloads: true,
    );

    $updated = ($this->makeModel)(id: 34, identifier: 'include-34', title: 'Updated');

    $driver->sync($repository, $request, $updated);

    expect(file_exists($absolutePath))->toBeFalse();
    expect($driver->all($repository, $request))->toBeNull();
});

it('invalidates unsafe partial collection payloads even when mutation strategy is disabled', function () {
    Typdy::$config = new TypdyConfig(
        team: 'team',
        project: 'project',
        privateStoragePath: $this->storagePath,
        mutateStoredPayloads: false,
    );

    $driver = new JsonDriver(new DocumentTransformer(($this->makeResolver)()));

    $repository = ($this->makeRepository)();

    $request = new Request(
        team: 'team',
        project: 'project',
        query: ['parameters' => ['all' => false, 'page[number]' => 1]],
    );

    $original = ($this->makeModel)(id: 88, identifier: 'partial-88', title: 'Original');

    $driver->sync($repository, $request, [$original]);

    $updated = ($this->makeModel)(id: 88, identifier: 'partial-88', title: 'Updated');

    $driver->sync($repository, new Request(team: 'team', project: 'project', id: 88), $updated);

    expect($driver->all($repository, $request))->toBeNull();
});

it('removes stale collection index entries when rewriting a collection file', function () {
    $driver = new JsonDriver(new DocumentTransformer(($this->makeResolver)()));

    $repository = ($this->makeRepository)();

    $request = new Request(
        team: 'team',
        project: 'project',
        query: ['parameters' => ['all' => true]],
    );

    $first = [
        ($this->makeModel)(id: 70, identifier: 'seventy', title: 'Seventy'),
        ($this->makeModel)(id: 71, identifier: 'seventy-one', title: 'Seventy One'),
    ];

    $second = [
        ($this->makeModel)(id: 70, identifier: 'seventy', title: 'Seventy Updated'),
    ];

    $driver->sync($repository, $request, $first);

    $before = array_values(array_filter(
        $driver->getIndexById($repository, 71),
        static fn (string $path): bool => str_contains($path, 'collections/'),
    ));

    expect($before)->not->toBeEmpty();

    $driver->sync($repository, $request, $second);

    $after = array_values(array_filter(
        $driver->getIndexById($repository, 71),
        static fn (string $path): bool => str_contains($path, 'collections/'),
    ));

    expect($after)->toBeEmpty();
    expect($driver->getIndexById($repository, 70))->not->toBeEmpty();
});

it('returns replayable requests for blueprint and construct id without duplicates', function () {
    $driver = new JsonDriver(new DocumentTransformer(($this->makeResolver)()));

    $repository = ($this->makeRepository)();

    $constructRequest = new Request(
        team: 'team',
        project: 'project',
        blueprint: 'test',
        id: 501,
        query: ['parameters' => ['include' => 'author']],
    );

    $collectionRequest = new Request(
        team: 'team',
        project: 'project',
        blueprint: 'test',
        query: ['parameters' => ['all' => true]],
    );

    $model = ($this->makeModel)(id: 501, identifier: 'replay-501', title: 'Replay');

    $driver->sync($repository, $constructRequest, $model);
    $driver->sync($repository, $collectionRequest, [$model]);

    $blueprintRequests = $driver->getReplayableRequests($repository, null);
    $targetRequests = $driver->getReplayableRequests($repository, 501);

    expect($blueprintRequests)->not->toBeEmpty();
    expect($targetRequests)->not->toBeEmpty();

    $blueprintHashes = array_values(array_unique(array_map(
        static fn (Request $request): string => md5(json_encode($request->toArray(), JSON_THROW_ON_ERROR)),
        $blueprintRequests,
    )));

    $targetHashes = array_values(array_unique(array_map(
        static fn (Request $request): string => md5(json_encode($request->toArray(), JSON_THROW_ON_ERROR)),
        $targetRequests,
    )));

    expect($blueprintRequests)->toHaveCount(count($blueprintHashes));
    expect($targetRequests)->toHaveCount(count($targetHashes));

    expect(array_values(array_filter(
        $targetRequests,
        static fn (Request $request): bool => $request->id === 501,
    )))->not->toBeEmpty();

    expect(array_values(array_filter(
        $targetRequests,
        static fn (Request $request): bool => ($request->query['parameters']['all'] ?? null) === true,
    )))->not->toBeEmpty();
});

it('returns null or empty results for unsupported lookup requests', function () {
    $driver = new JsonDriver(new DocumentTransformer(($this->makeResolver)()));

    $repository = ($this->makeRepository)();

    $request = new Request(
        team: 'team',
        project: 'project',
        query: ['parameters' => ['all' => false, 'page[number]' => 1, 'include' => 'test']],
    );

    expect($driver->find($repository, new Request('team', 'project')))->toBeNull();
    expect($driver->all($repository, new Request('team', 'project', id: 1)))->toBeNull();
    expect($driver->getMetadata($repository, new Request('team', 'project', id: 404))->raw)->toBeEmpty();
    expect($driver->getIndexById($repository, 404))->toBeEmpty();
    expect($driver->getIndexByBlueprint($repository, 'unknown'))->toBeEmpty();
});
