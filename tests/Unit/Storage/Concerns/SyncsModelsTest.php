<?php

declare(strict_types=1);

use Typdy\StarterKit\Attributes\Blueprint;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Models\Model;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\Data\Request;
use Typdy\StarterKit\Storage\Concerns\SyncsModels;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\TypdyConfig;

beforeEach(function () {
    Typdy::$config = new TypdyConfig(
        team: 'team',
        project: 'project',
        privateStoragePath: sys_get_temp_dir(),
    );

    $this->makeRepository = fn () => mock(Collection::class)
        ->shouldReceive('getSignature')
        ->andReturn('team:project:test')
        ->getMock();

    $this->makeModel = function (
        int $id,
        string $identifier,
        string $title,
    ) {
        $resource = new Resource(
            type: 'test',
            id: (string) $id,
            attributes: [
                'identifier' => $identifier,
                'title' => $title,
            ],
        );

        $model = new
            #[Blueprint('test')]
            class extends Model {
                public ?string $title = null;
            };

        return $model->hydrateFromResource($resource);
    };
});

it('delegates single-model syncs to construct storage writes', function () {
    $harness = new class {
        use SyncsModels;

        public array $singleWrites = [];

        public array $collectionWrites = [];

        protected function writeCollectionToStorage(
            Collection $repository,
            Request $request,
            iterable $models,
            array $meta,
        ): void {
            $this->collectionWrites[] = compact('models', 'request');
        }

        protected function writeConstructToStorage(
            Collection $repository,
            Request $request,
            Construct $model,
            array $meta,
        ): void {
            $this->singleWrites[] = compact('model', 'request');
        }
    };

    $repository = ($this->makeRepository)();

    $request = new Request('test-team', 'test-project');

    $model = ($this->makeModel)(id: 1, identifier: 'hello-world', title: 'Hello');

    $result = $harness->sync($repository, $request, $model);

    expect($result)->toBe($model);
    expect($harness->singleWrites)->toHaveCount(1);
    expect($harness->collectionWrites)->toHaveCount(0);
});

it('writes collection syncs and simplified construct variants', function () {
    $harness = new class {
        use SyncsModels;

        public array $singleWrites = [];

        public array $collectionWrites = [];

        protected function writeCollectionToStorage(
            Collection $repository,
            Request $request,
            iterable $models,
            array $meta,
        ): void {
            $this->collectionWrites[] = compact('models', 'request');
        }

        protected function writeConstructToStorage(
            Collection $repository,
            Request $request,
            Construct $model,
            array $meta,
        ): void {
            $this->singleWrites[] = compact('model', 'request');
        }
    };

    $repository = ($this->makeRepository)();

    $models = [
        ($this->makeModel)(id: 1, identifier: 'one', title: 'One'),
        ($this->makeModel)(id: 2, identifier: 'two', title: 'Two'),
    ];

    $request = new Request('test-team', 'test-project', query: ['parameters' => [
        'all' => true,
        'sort' => 'title',
        'page[number]' => 2,
        'filter[status]' => 'active',
    ]]);

    $result = $harness->sync($repository, $request, $models);

    expect($result)->toBe($models);
    expect($harness->collectionWrites)->toHaveCount(1);
    expect($harness->singleWrites)->toHaveCount(4);
});

it('supports syncing generator-based collections safely', function () {
    $harness = new class {
        use SyncsModels;

        public array $singleWrites = [];

        public array $collectionWrites = [];

        protected function writeCollectionToStorage(
            Collection $repository,
            Request $request,
            iterable $models,
            array $meta,
        ): void {
            $this->collectionWrites[] = compact('models', 'request');
        }

        protected function writeConstructToStorage(
            Collection $repository,
            Request $request,
            Construct $model,
            array $meta,
        ): void {
            $this->singleWrites[] = compact('model', 'request');
        }
    };

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

    $request = new Request('test-team', 'test-project');

    $result = $harness->sync($repository, $request, $generator);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(2);
    expect($harness->collectionWrites)->toHaveCount(1);
    expect($harness->singleWrites)->toHaveCount(2);
});
